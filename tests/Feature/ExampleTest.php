<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// The home page reads system_settings (branding, theme, menus), so this needs a migrated
// database. Without it the test failed on a clean checkout — for a while behind a 404,
// because the dev APP_URL points at a subdirectory and the test client resolved '/'
// against it; phpunit.xml now pins APP_URL to the domain root, which exposed the real
// missing-table error underneath.
uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
