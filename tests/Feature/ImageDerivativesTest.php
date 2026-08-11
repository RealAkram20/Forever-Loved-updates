<?php

use App\Helpers\ResponsiveImage;
use App\Services\ImageDerivativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('builds the ladder of scaled derivatives for an uploaded photo', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('photo.jpg', 2000, 1500)->store('memorials/1/gallery', 'public');

    $made = app(ImageDerivativeService::class)->generate($path);

    expect($made)->toHaveCount(4);
    foreach ([160, 480, 960, 1600] as $width) {
        $derivative = ImageDerivativeService::derivativePath($path, $width);
        Storage::disk('public')->assertExists($derivative);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($derivative));
        expect($w)->toBe($width);
    }
});

it('never upscales a small original', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('small.jpg', 500, 500)->store('memorials/1/gallery', 'public');

    $made = app(ImageDerivativeService::class)->generate($path);

    // 160 and 480 fit inside 500px; 960 and 1600 would be invented pixels.
    expect($made)->toHaveCount(2);
    Storage::disk('public')->assertMissing(ImageDerivativeService::derivativePath($path, 960));
});

it('serves only rungs that exist, and the original before any exist', function () {
    Storage::fake('public');

    $service = app(ImageDerivativeService::class);
    $path = UploadedFile::fake()->image('photo.jpg', 1200, 900)->store('memorials/1/gallery', 'public');

    // Nothing derived yet: attrs degrade to a bare src of the original.
    expect($service->variants($path))->toBeNull()
        ->and((string) ResponsiveImage::attrs($path, '50vw'))->toContain('src=')
        ->and((string) ResponsiveImage::attrs($path, '50vw'))->not->toContain('srcset');

    $service->generate($path);

    $attrs = (string) ResponsiveImage::attrs($path, '50vw');
    expect($attrs)->toContain('srcset=')
        ->and($attrs)->toContain('160w')
        ->and($attrs)->toContain('960w')
        ->and($attrs)->toContain('sizes="50vw"');

    // Single-URL form picks the smallest rung at or above the wanted width.
    expect(ResponsiveImage::url($path, 160))->toContain('-160.webp')
        ->and(ResponsiveImage::url($path, 1600))->toContain('-960.webp');
});

it('removes derivatives along with their source', function () {
    Storage::fake('public');

    $service = app(ImageDerivativeService::class);
    $path = UploadedFile::fake()->image('photo.jpg', 800, 600)->store('memorials/1/gallery', 'public');
    $service->generate($path);

    Storage::disk('public')->assertExists(ImageDerivativeService::derivativePath($path, 480));

    $service->delete($path);

    Storage::disk('public')->assertMissing(ImageDerivativeService::derivativePath($path, 160));
    Storage::disk('public')->assertMissing(ImageDerivativeService::derivativePath($path, 480));
});

it('leaves videos and gifs alone', function () {
    expect(ImageDerivativeService::eligible('memorials/1/gallery/clip.mp4'))->toBeFalse()
        ->and(ImageDerivativeService::eligible('memorials/1/gallery/anim.gif'))->toBeFalse()
        ->and(ImageDerivativeService::eligible('memorials/1/gallery/photo.jpeg'))->toBeTrue();
});

it('backfills existing uploads and skips output folders', function () {
    Storage::fake('public');

    $service = app(ImageDerivativeService::class);
    $old = UploadedFile::fake()->image('old.jpg', 1000, 800)->store('memorials/1/gallery', 'public');
    $service->generate($old); // pre-existing output must not become input

    $new = UploadedFile::fake()->image('new.png', 900, 900)->store('memorials/2/gallery', 'public');

    $this->artisan('images:derivatives')->assertSuccessful();

    Storage::disk('public')->assertExists(ImageDerivativeService::derivativePath($new, 480));

    // No derivative-of-a-derivative appeared.
    expect(collect(Storage::disk('public')->allFiles())->filter(fn ($p) => str_contains($p, '/d/d/')))->toBeEmpty();
});
