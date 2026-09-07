<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * The admin's destructive buttons ask through our own dialog, not the browser's.
 *
 * The first test is the one that guards the future: it reads the admin views off disk and
 * fails on any `confirm(` that comes back. A styled dialog that three-quarters of the screens
 * use is a dialog nobody can rely on.
 */
uses(RefreshDatabase::class);

it('leaves no native confirm() in any admin view', function () {
    $roots = [resource_path('views/pages'), resource_path('views/components'), resource_path('views/partials')];
    $offenders = [];

    foreach ($roots as $root) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            // Public-facing pages are not the admin and are not converted here; the dialog
            // itself necessarily mentions the word.
            if (str_contains($path, 'memorials/public') || str_contains($path, '/visitor/') || str_contains($path, 'confirm-dialog')) {
                continue;
            }
            if (preg_match('/\bconfirm\(/', file_get_contents($path))) {
                $offenders[] = str_replace(str_replace('\\', '/', base_path()).'/', '', $path);
            }
        }
    }

    expect($offenders)->toBe([], 'native confirm() still used in: '.implode(', ', $offenders));
});

it('mounts the dialog once in the admin layout and wires the users page to it', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    User::factory()->create(['name' => 'Grace Namutebi']);

    $html = $this->actingAs($admin)->get(route('users.index'))->assertOk()->getContent();

    expect(substr_count($html, 'aria-labelledby="confirm-dialog-title"'))->toBe(1)
        ->and($html)->toContain('Alpine.store(\'confirm\'')
        ->and($html)->toContain('Alpine.directive(\'confirm\'')
        ->and($html)->toContain('x-confirm="')
        ->and($html)->not->toContain('return confirm(');
});

it('keeps a quoted name valid inside the attribute, as the browser will read it', function () {
    // The old attribute wrapped names in addslashes() for the JS string. The conversion keeps
    // that wrapper. What matters is not which entity Blade emits for the quote, but what the
    // browser hands Alpine after decoding the attribute: a JS string in which the apostrophe
    // is escaped. So decode the attribute the way a browser does, then look at the result.
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');
    User::factory()->create(['name' => "Ng\u{169}g\u{129} wa Thiong'o"]);

    $html = $this->actingAs($admin)->get(route('users.index'))->assertOk()->getContent();

    preg_match('/x-confirm="([^"]*Thiong[^"]*)"/u', $html, $m);
    expect($m)->not->toBeEmpty('no x-confirm attribute mentions the quoted name');

    $asAlpineSeesIt = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // A backslash-escaped apostrophe inside the single-quoted JS string — valid, and the
    // dialog will show the name with its apostrophe intact.
    expect($asAlpineSeesIt)->toContain("Thiong\\'o")
        ->and($asAlpineSeesIt)->toStartWith("'")
        ->and(substr_count($asAlpineSeesIt, "'") % 2)->toBe(1); // unescaped quotes: opening + closing + one escaped = odd count
});
