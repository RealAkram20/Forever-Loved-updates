<?php

use App\Models\Memorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    Storage::fake('public');
});

function coverUrl(Memorial $memorial): string
{
    return 'http://localhost/m/'.$memorial->slug.'/cover-photo';
}

it('lets the owner upload a cover banner and records its size', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    test()->actingAs($owner)
        ->post(coverUrl($memorial), ['photo' => UploadedFile::fake()->image('banner.jpg', 1600, 500)])
        ->assertOk()
        ->assertJson(['success' => true]);

    $memorial->refresh();

    expect($memorial->cover_photo_path)->not->toBeNull()
        ->and($memorial->cover_photo_size)->toBeGreaterThan(0)
        ->and($memorial->cover_photo_url)->toContain($memorial->cover_photo_path);

    Storage::disk('public')->assertExists($memorial->cover_photo_path);
});

it('deletes the previous file when the cover is replaced', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    test()->actingAs($owner)->post(coverUrl($memorial), ['photo' => UploadedFile::fake()->image('first.jpg')])->assertOk();
    $first = $memorial->fresh()->cover_photo_path;

    test()->actingAs($owner)->post(coverUrl($memorial), ['photo' => UploadedFile::fake()->image('second.jpg')])->assertOk();
    $second = $memorial->fresh()->cover_photo_path;

    // Without this the old banner stays on disk forever, counted against nothing.
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('removes the cover and clears its recorded size', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    test()->actingAs($owner)->post(coverUrl($memorial), ['photo' => UploadedFile::fake()->image('banner.jpg')])->assertOk();
    $path = $memorial->fresh()->cover_photo_path;

    test()->actingAs($owner)->deleteJson(coverUrl($memorial))->assertOk()->assertJson(['success' => true]);

    $memorial->refresh();

    expect($memorial->cover_photo_path)->toBeNull()
        ->and($memorial->cover_photo_size)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('refuses a cover upload from someone who cannot edit the memorial', function () {
    $stranger = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    test()->actingAs($stranger)
        ->post(coverUrl($memorial), ['photo' => UploadedFile::fake()->image('banner.jpg')])
        ->assertStatus(403);

    expect($memorial->fresh()->cover_photo_path)->toBeNull();
});

it('refuses a cover removal from someone who cannot edit the memorial', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    test()->actingAs($owner)->post(coverUrl($memorial), ['photo' => UploadedFile::fake()->image('banner.jpg')])->assertOk();
    $path = $memorial->fresh()->cover_photo_path;

    test()->actingAs($stranger)->deleteJson(coverUrl($memorial))->assertStatus(403);

    expect($memorial->fresh()->cover_photo_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

it('rejects a file that is not an image', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    test()->actingAs($owner)
        ->postJson(coverUrl($memorial), ['photo' => UploadedFile::fake()->create('payload.pdf', 20, 'application/pdf')])
        ->assertStatus(422);

    expect($memorial->fresh()->cover_photo_path)->toBeNull();
});

it('lets an admin set a cover on a memorial they do not own', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    test()->actingAs($admin)
        ->post(coverUrl($memorial), ['photo' => UploadedFile::fake()->image('banner.jpg')])
        ->assertOk();

    expect($memorial->fresh()->cover_photo_path)->not->toBeNull();
});
