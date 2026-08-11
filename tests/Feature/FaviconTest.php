<?php

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * /favicon.ico is what WhatsApp previews and old crawlers actually fetch — they never
 * read <link rel="icon"> — so it must serve the favicon configured in admin settings,
 * not a static file no setting can touch.
 */
it('serves the admin-configured favicon at /favicon.ico', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('brand.png', 64, 64)->store('branding', 'public');
    SystemSetting::set('branding.favicon_path', $path);

    $this->get('/favicon.ico')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('falls back to the default icon when nothing is configured', function () {
    $this->get('/favicon.ico')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/x-icon');
});
