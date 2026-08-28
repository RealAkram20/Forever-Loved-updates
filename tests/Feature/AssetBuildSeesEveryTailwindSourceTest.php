<?php

/**
 * Everything Tailwind is told to scan has to reach the stage that runs the build.
 *
 * This failed silently and expensively. `resources/css/app.css` carries
 * `@source '../../themes/**‍/*.blade.php'` because templates live at the project root, but the
 * Dockerfile's asset stage copied only vite.config.js, resources and public. The build
 * succeeded, produced a bundle 19KB smaller than the local one, and shipped.
 *
 * Nothing looked wrong until a reseller applied the second template: `hidden lg:block` never
 * got its `lg:block`, so their navigation rendered at zero height; the services grid and the
 * footer fell back to inherited colours and drew dark text on dark and light on light. The
 * markup was all present. None of it could be read. No test caught it, because locally the
 * build sees the whole working tree and everything is fine.
 *
 * So this asserts the two files agree: every directory app.css declares as a source, outside
 * the ones deliberately excluded below, is copied into the stage that runs `npm run build`.
 * It is cheap, and it covers the next @source somebody adds as well as this one.
 */

/** The asset stage of the Dockerfile — from `FROM ... AS assets` to the next FROM. */
function assetStage(): string
{
    $dockerfile = file_get_contents(dirname(__DIR__, 2).'/Dockerfile');

    preg_match('/^FROM .*AS assets$(.*?)^FROM /ms', $dockerfile, $m);

    return $m[1] ?? '';
}

/**
 * Top-level directories app.css asks Tailwind to scan, as paths relative to the project root.
 *
 * @return array<int, string>
 */
function tailwindSourceDirs(): array
{
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    preg_match_all("/@source\s+'([^']+)'/", $css, $m);

    $dirs = [];

    foreach ($m[1] as $glob) {
        // app.css sits in resources/css, so '../..' is the project root and '..' is resources.
        $path = preg_replace('#^\.\./\.\./#', '', $glob, 1, $wentUpTwice);

        // A single '..' stays inside resources/, which is already copied.
        if (! $wentUpTwice) {
            continue;
        }

        $dirs[] = strtok($path, '/');
    }

    return array_values(array_unique($dirs));
}

it('copies every scanned directory into the asset build stage', function () {
    $stage = assetStage();

    expect($stage)->not->toBe('', 'the Dockerfile has no `AS assets` stage any more');

    // vendor and storage are deliberately absent: vendor is installed in its own stage and
    // storage/framework/views is a local compile cache that a clean build has no equivalent
    // of. Neither contributes a class the application relies on.
    $excluded = ['vendor', 'storage'];

    foreach (tailwindSourceDirs() as $dir) {
        if (in_array($dir, $excluded, true)) {
            continue;
        }

        // str_contains inside toBeTrue rather than expect(...)->toContain(...): toContain
        // takes needles, not a message, so a second argument there would assert the
        // Dockerfile also contains the explanation.
        expect(str_contains($stage, "COPY {$dir} ./{$dir}"))->toBeTrue(
            "resources/css/app.css scans {$dir}/, but the Dockerfile's asset stage never copies it — "
            .'the build will silently omit every utility class used only there'
        );
    }
});

it('still scans the themes directory at all', function () {
    // The other half of the same guarantee. Moving templates without moving this line gives
    // them no CSS whatsoever, which is louder than it sounds: the page renders unstyled.
    expect(tailwindSourceDirs())->toContain('themes');
});
