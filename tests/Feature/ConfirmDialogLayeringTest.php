<?php

/**
 * A confirm dialog must be the topmost thing on the screen while it is open.
 *
 * The memorial page asks its confirm from inside its own modals -- the gallery categories
 * editor, the caption editor, the lightbox -- and for a while the confirm shared a z-index with
 * two of them and sat below the third. Equal z falls back to DOM order, and the layout's dialog
 * is declared before the page content, so the editor painted over the question it had just
 * raised: a family deleting a category saw the categories panel and had to press Done to find
 * Delete/Cancel underneath. The admin's dialog had the same latent fault against a popover and
 * the checkout modals.
 *
 * Read off disk rather than rendered: the numbers are in class attributes, and the failure mode
 * is somebody adding a new modal "at 99998 like the others" or nudging the lightbox up. Either
 * should fail here before it fails in front of a family.
 */
function zIndexesIn(string $path): array
{
    preg_match_all('/\bz-\[?(\d+)\]?/', file_get_contents($path), $m);

    return array_map('intval', $m[1]);
}

function zIndexOfElement(string $path, string $attrNeedle): int
{
    $html = file_get_contents($path);
    // The element's opening tag, found by a distinctive attribute, then its z utility.
    preg_match('/<[^>]*'.preg_quote($attrNeedle, '/').'[^>]*>/', $html, $tag);

    expect($tag)->not->toBeEmpty("no element with {$attrNeedle} in ".basename($path));

    preg_match('/\bz-\[?(\d+)\]?/', $tag[0], $z);

    expect($z)->not->toBeEmpty("no z-index on the element with {$attrNeedle} in ".basename($path));

    return (int) $z[1];
}

function bladeFilesUnder(array $roots): array
{
    $files = [];

    foreach ($roots as $root) {
        if (is_file($root)) {
            $files[] = $root;

            continue;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    return array_map(fn ($p) => str_replace(DIRECTORY_SEPARATOR, '/', $p), $files);
}

it('keeps the memorial confirm above every z-index the memorial page and its script use', function () {
    $confirm = zIndexOfElement(resource_path('views/layouts/fullscreen-layout.blade.php'), 'id="confirm-dialog-backdrop"');

    $page = array_merge(
        zIndexesIn(resource_path('views/pages/memorials/public.blade.php')),
        zIndexesIn(resource_path('js/memorial-public.js')),
    );

    // Strictly above, not equal: equal is exactly the bug.
    $highest = max($page);
    expect($confirm)->toBeGreaterThan($highest, "memorial confirm is z-{$confirm} but the page reaches z-{$highest}");
});

it('keeps it above the other overlays the fullscreen layout itself draws', function () {
    $layout = resource_path('views/layouts/fullscreen-layout.blade.php');
    $confirm = zIndexOfElement($layout, 'id="confirm-dialog-backdrop"');

    $others = array_filter(zIndexesIn($layout), fn (int $z) => $z !== $confirm);

    expect($confirm)->toBeGreaterThan(max($others ?: [0]));
});

it('keeps the admin dialog above every z-index the admin views and layout use', function () {
    $confirm = zIndexOfElement(resource_path('views/partials/confirm-dialog.blade.php'), 'aria-labelledby="confirm-dialog-title"');

    $highest = 0;

    foreach (bladeFilesUnder([resource_path('views/pages'), resource_path('views/components'), resource_path('views/layouts/app.blade.php')]) as $path) {
        // The memorial public page has its own layout and its own dialog; covered above.
        if (str_contains($path, 'memorials/public')) {
            continue;
        }

        foreach (zIndexesIn($path) as $z) {
            $highest = max($highest, $z);
        }
    }

    expect($confirm)->toBeGreaterThan($highest, "admin confirm is z-{$confirm} but admin views reach z-{$highest}");
});

/**
 * The source number is not enough. Tailwind emits an arbitrary-value class only if it saw it at
 * build time, so `z-[100000]` written into a Blade file and not rebuilt is a class with no rule:
 * the browser computes `auto`, and the dialog sits under the editor exactly as before while
 * every test above passes. Found by rendering, after the fix "worked" on paper. So the built
 * stylesheet is checked too.
 */
function builtAppCss(): string
{
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $entry = $manifest['resources/css/app.css']['file'] ?? null;

    expect($entry)->not->toBeNull('resources/css/app.css missing from build manifest');

    return file_get_contents(public_path('build/'.$entry));
}

it('has both dialog z-indexes compiled into the built stylesheet, not just written in the source', function () {
    $css = builtAppCss();

    $memorial = zIndexOfElement(resource_path('views/layouts/fullscreen-layout.blade.php'), 'id="confirm-dialog-backdrop"');
    $admin = zIndexOfElement(resource_path('views/partials/confirm-dialog.blade.php'), 'aria-labelledby="confirm-dialog-title"');

    foreach ([$memorial, $admin] as $z) {
        // toBeTrue, not toContain(needle, message): Pest's toContain treats a second
        // argument as a second needle, not a failure message. Tailwind escapes the brackets
        // in the selector, so the literal is .z-\[<n>\].
        expect(str_contains($css, '.z-\['.$z.'\]'))
            ->toBeTrue("z-[{$z}] is in the source but not in the built CSS — run npm run build and commit public/build");
    }
});
