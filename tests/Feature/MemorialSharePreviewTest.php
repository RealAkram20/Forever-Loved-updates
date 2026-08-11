<?php

use App\Helpers\MemorialShareMetaHelper;
use App\Models\Memorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * The link-preview contract: WhatsApp drops og:images over ~600KB, so a memorial must
 * never offer the raw upload — it offers a 1200×630 derivative, dimensions declared.
 */
it('shares a crawler-sized derivative instead of the raw profile photo', function () {
    Storage::fake('public');

    $photo = UploadedFile::fake()->image('portrait.jpg', 2000, 2600);
    $path = $photo->store('memorials/1/profile', 'public');

    $memorial = Memorial::factory()->create([
        'is_public' => true,
        'profile_photo_path' => $path,
    ]);

    $meta = MemorialShareMetaHelper::forMemorial($memorial);

    expect($meta['image'])->toContain('/share/og-')
        ->and($meta['image'])->toEndWith('.jpg')
        ->and($meta['image_width'])->toBe(1200)
        ->and($meta['image_height'])->toBe(630)
        ->and($meta['type'])->toBe('profile');

    $derivative = 'memorials/1/profile/share/og-'.md5($path).'.jpg';
    Storage::disk('public')->assertExists($derivative);

    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($derivative));
    expect($w)->toBe(1200)->and($h)->toBe(630);
});

it('falls back to the original URL when the photo is missing from disk', function () {
    Storage::fake('public');

    $memorial = Memorial::factory()->create([
        'is_public' => true,
        'profile_photo_path' => 'memorials/1/profile/long-gone.jpg',
    ]);

    $meta = MemorialShareMetaHelper::forMemorial($memorial);

    expect($meta)->not->toHaveKey('image_width');
});

it('renders the derivative and its dimensions into the page head', function () {
    Storage::fake('public');

    $photo = UploadedFile::fake()->image('portrait.jpg', 1600, 1600);
    $path = $photo->store('memorials/1/profile', 'public');

    $memorial = Memorial::factory()->create([
        'is_public' => true,
        'profile_photo_path' => $path,
    ]);

    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertSee('/share/og-', false)
        ->assertSee('og:image:width', false)
        ->assertSee('property="og:type" content="profile"', false);
});

it('keeps word boundaries when a biography with markup becomes the description', function () {
    $memorial = Memorial::factory()->create([
        'is_public' => true,
        'biography' => '<p>A victim of crime.</p><table><tr><td>Born</td><td>March 24, 2000</td></tr></table>',
    ]);

    $meta = MemorialShareMetaHelper::forMemorial($memorial);

    expect($meta['description'])->toContain('crime. Born March 24')
        ->and($meta['description'])->not->toContain('crime.Born');
});
