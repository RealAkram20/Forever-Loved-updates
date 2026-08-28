<?php

use App\Models\Theme;
use App\Themes\ThemeRegistry;
use App\Themes\ThemeShadows;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The check that catches what the conformance suite cannot.
 *
 * ThemeConformanceTest asserts that every template still *renders*. A drifted template renders
 * perfectly — it serves the version of a view it was written against, months after the
 * original was fixed. There is no exception, no 500, no failing assertion anywhere: the only
 * evidence is a funeral home's site quietly missing a bug fix everyone else got.
 *
 * So the property under test here is not "the command produces output". It is that the command
 * **fails** — non-zero, gate-the-build fails — in each of the four ways a template can be out
 * of step, and passes in the one way it can be in step.
 *
 * Every test runs against its own fixture template and names it explicitly, so nothing here
 * depends on which real templates happen to be on disk, or on the fixture ResellerThemeTest
 * creates in the same process.
 */
const DOCTOR_TEMPLATE = 'zz-doctor-template';

/** A real view the fixture shadows. Chosen because a template genuinely would shadow it. */
const DOCTOR_SHADOWED = 'layouts/visitor.blade.php';

function doctorPath(string $suffix = ''): string
{
    return dirname(__DIR__, 2).'/themes/'.DOCTOR_TEMPLATE.($suffix === '' ? '' : '/'.$suffix);
}

/**
 * Write the fixture's manifest.
 *
 * @param  array<string, string>|null  $shadows  null writes no `shadows` key at all, which is
 *                                               the state a template is in before anyone has
 *                                               ever recorded a baseline for it.
 */
function doctorManifest(?array $shadows): void
{
    $manifest = ['name' => 'Doctor Fixture', 'description' => 'Exists only while this file runs.'];

    if ($shadows !== null) {
        $manifest['shadows'] = $shadows;
    }

    file_put_contents(doctorPath('theme.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/** The fingerprint the fixture's shadow *should* carry when it is in step. */
function doctorTrueFingerprint(): string
{
    return ThemeShadows::fingerprint(resource_path('views/'.DOCTOR_SHADOWED));
}

/**
 * The template has to exist on disk before the first test's application is built.
 *
 * ThemeRegistry is a container singleton and memoises the directory listing on first use — a
 * per-request cache in production, an until-the-process-ends cache here. A template created
 * after boot is simply invisible to it, and the command answers "no template 'x'" rather than
 * checking anything. Its *manifest* can still be rewritten per test, because ThemeShadows
 * reads that file raw rather than through the registry.
 *
 * No application exists yet inside beforeAll, so paths are built from __DIR__ rather than from
 * base_path()/resource_path().
 */
beforeAll(function () {
    $root = dirname(__DIR__, 2);

    @mkdir($root.'/themes/'.DOCTOR_TEMPLATE.'/layouts', 0777, true);
    @mkdir($root.'/themes/'.DOCTOR_TEMPLATE.'/sections', 0777, true);

    // Shadows a real default view. Its *contents* deliberately differ from the original —
    // that is what a template is for — so a check that compared the two would report every
    // template as broken. What is compared is the original against its recorded baseline.
    file_put_contents($root.'/themes/'.DOCTOR_TEMPLATE.'/'.DOCTOR_SHADOWED, '<div>A visitor layout of our own.</div>');

    // Shadows nothing: a view this template brings itself. Must never be reported.
    file_put_contents($root.'/themes/'.DOCTOR_TEMPLATE.'/sections/doctor-only.blade.php', '<div>Ours alone.</div>');

    file_put_contents(
        $root.'/themes/'.DOCTOR_TEMPLATE.'/theme.json',
        json_encode(['name' => 'Doctor Fixture', 'description' => 'Exists only while this file runs.'], JSON_PRETTY_PRINT)
    );
});

afterAll(function () {
    $root = dirname(__DIR__, 2).'/themes/'.DOCTOR_TEMPLATE;

    foreach ([DOCTOR_SHADOWED, 'sections/doctor-only.blade.php', 'theme.json'] as $file) {
        @unlink($root.'/'.$file);
    }

    @rmdir($root.'/layouts');
    @rmdir($root.'/sections');
    @rmdir($root);
});

beforeEach(function () {
    // Reset to "in step" so each test starts from the state a healthy template is in and
    // moves exactly one thing.
    doctorManifest([DOCTOR_SHADOWED => doctorTrueFingerprint()]);
});

/*
|--------------------------------------------------------------------------
| In step, and the four ways of not being
|--------------------------------------------------------------------------
*/

it('passes a template whose baseline still matches the original', function () {
    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE])
        ->assertExitCode(0);
});

it('fails when the shadowed original has changed since the template was written', function () {
    // The whole point of the command, and the only failure mode with no other symptom. A
    // baseline that no longer matches means the original moved on and this template did not.
    doctorManifest([DOCTOR_SHADOWED => 'deadbeefdeadbeef']);

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE])
        ->expectsOutputToContain('drifted')
        ->assertExitCode(1);
});

it('fails when a shadow has no baseline at all', function () {
    // Not drift — worse. Nothing can be said about it either way, and a template nobody has
    // baselined is one drift will never be reported for.
    doctorManifest(null);

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE])
        ->expectsOutputToContain('unrecorded')
        ->assertExitCode(1);
});

it('fails on a baseline for a view the template no longer shadows', function () {
    doctorManifest([
        DOCTOR_SHADOWED => doctorTrueFingerprint(),
        'layouts/gone.blade.php' => 'deadbeefdeadbeef',
    ]);

    // A stale entry reads as "checked and fine" to anyone skimming the manifest, which makes
    // it worse than a missing one.
    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE])
        ->expectsOutputToContain('stale')
        ->assertExitCode(1);
});

it('leaves a view the template brings itself out of the report entirely', function () {
    // sections/doctor-only.blade.php shadows nothing, so it can never drift. If it were
    // treated as a shadow it would be permanently 'unrecorded' and the command would fail
    // forever — which is how a check gets switched off.
    $scan = ThemeShadows::scan(DOCTOR_TEMPLATE);

    expect($scan['own'])->toContain('sections/doctor-only.blade.php')
        ->and($scan['shadows'])->not->toHaveKey('sections/doctor-only.blade.php')
        ->and($scan['shadows'])->toHaveKey(DOCTOR_SHADOWED);
});

/*
|--------------------------------------------------------------------------
| Recording the baseline
|--------------------------------------------------------------------------
*/

it('records a baseline that makes the same check pass', function () {
    doctorManifest(null);

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE])->assertExitCode(1);

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE, '--record' => true])
        ->assertExitCode(0);

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE])->assertExitCode(0);
});

it('records the original fingerprint, never the template own copy', function () {
    // The distinction the whole design rests on. Recording the template's own file would make
    // every template permanently "in step" with itself and detect nothing, ever.
    doctorManifest(null);

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE, '--record' => true]);

    $recorded = ThemeShadows::recorded(DOCTOR_TEMPLATE);

    expect($recorded[DOCTOR_SHADOWED])->toBe(doctorTrueFingerprint())
        ->and($recorded[DOCTOR_SHADOWED])->not->toBe(ThemeShadows::fingerprint(doctorPath(DOCTOR_SHADOWED)));
});

it('drops stale entries when re-recording', function () {
    doctorManifest([
        DOCTOR_SHADOWED => doctorTrueFingerprint(),
        'layouts/gone.blade.php' => 'deadbeefdeadbeef',
    ]);

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE, '--record' => true]);

    expect(ThemeShadows::recorded(DOCTOR_TEMPLATE))->not->toHaveKey('layouts/gone.blade.php');
});

it('keeps everything else in the manifest when recording', function () {
    // --record rewrites the file. A template's tokens and default pages living in the same
    // file means a careless write would silently unstyle every site using it.
    file_put_contents(doctorPath('theme.json'), json_encode([
        'name' => 'Doctor Fixture',
        'tokens' => ['branding.primary_color' => '#BB1520'],
        'default_pages' => ['about' => ['widgets' => [['type' => 'heading', 'props' => []]]]],
    ], JSON_PRETTY_PRINT));

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE, '--record' => true]);

    $manifest = (new ThemeRegistry)->manifest(DOCTOR_TEMPLATE);

    expect($manifest->tokens)->toBe(['branding.primary_color' => '#BB1520'])
        ->and($manifest->defaultPages)->toHaveKey('about')
        ->and($manifest->shadows)->toHaveKey(DOCTOR_SHADOWED);
});

/*
|--------------------------------------------------------------------------
| Things that would make the check lie
|--------------------------------------------------------------------------
*/

it('fingerprints the same on Windows and on Linux', function () {
    // This repository is developed on Windows and deployed on Linux. Without normalising line
    // endings, every shadow reads as drifted on whichever platform did not record it — and a
    // check that fails on a fresh clone is one everybody learns to pass with --record.
    $crlf = sys_get_temp_dir().'/doctor-crlf.blade.php';
    $lf = sys_get_temp_dir().'/doctor-lf.blade.php';

    file_put_contents($crlf, "<div>\r\n  line\r\n</div>\r\n");
    file_put_contents($lf, "<div>\n  line\n</div>\n");

    expect(ThemeShadows::fingerprint($crlf))->toBe(ThemeShadows::fingerprint($lf));

    @unlink($crlf);
    @unlink($lf);
});

it('refuses blades under the base template', function () {
    // `basic` *is* resources/views. A blade here would serve the platform its own website
    // through the reseller theme system — the arrangement that was built once and reverted.
    // Asserting it costs nothing; rediscovering it cost a revert.
    $stray = ThemeRegistry::path(ThemeRegistry::BASE).'/layouts/visitor.blade.php';

    @mkdir(dirname($stray), 0777, true);
    file_put_contents($stray, '<div>Should not exist.</div>');

    try {
        $this->artisan('themes:doctor', ['template' => ThemeRegistry::BASE])
            ->expectsOutputToContain('It must ship a manifest and nothing else')
            ->assertExitCode(1);
    } finally {
        @unlink($stray);
        @rmdir(dirname($stray));
    }
});

it('says when a template on disk is not selectable by anyone', function () {
    // On disk, no catalogue row: the gallery does not offer it, and nothing distinguishes
    // that from "we never shipped it" unless somebody is told.
    //
    // Dropping the row first is the point of the setup, not a workaround. The seed migration
    // calls ThemeCatalogue::sync(), so a template present at migrate time is already
    // selectable — which is the behaviour that makes this warning rare, and worth having when
    // it does fire (a template added to a running deploy, or a sync that failed).
    Theme::whereNull('reseller_id')->where('template', DOCTOR_TEMPLATE)->delete();

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE])
        ->expectsOutputToContain('not in the catalogue')
        // A warning, not a failure: it is database state, and the database CI runs against is
        // not the one serving anybody's site.
        ->assertExitCode(0);
});

it('fails on a catalogue disagreement only when asked to', function () {
    Theme::whereNull('reseller_id')->where('template', DOCTOR_TEMPLATE)->delete();

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE, '--strict' => true])
        ->assertExitCode(1);
});

it('says when a template promises a screenshot it does not ship', function () {
    // Found live: Dignified declared preview.webp and shipped none, so every gallery card for
    // it rendered a broken image — on the one screen whose entire job is showing what a theme
    // looks like. No test saw it, because the page still returned 200.
    file_put_contents(
        doctorPath('theme.json'),
        json_encode([
            'name' => 'Doctor Fixture',
            'screenshot' => 'preview.webp',
            'shadows' => [DOCTOR_SHADOWED => doctorTrueFingerprint()],
        ], JSON_PRETTY_PRINT)
    );

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE])
        ->expectsOutputToContain('does not ship it')
        // Advisory, not drift: a missing image is cosmetic, and failing a build over one
        // teaches people to pass --no-verify. --strict is there for the check that should.
        ->assertExitCode(0);

    $this->artisan('themes:doctor', ['template' => DOCTOR_TEMPLATE, '--strict' => true])
        ->assertExitCode(1);
});
