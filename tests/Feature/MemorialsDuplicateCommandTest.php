<?php

/**
 * Copying a memorial onto another tenant's site.
 *
 * This command is run by hand against production, on a page about somebody who died, so the
 * things worth pinning are the ones that would be discovered by a family rather than by us:
 * a copy that quietly points back at the original's albums, a duplicate slug, invented
 * traffic, or subscribers notified about a memorial they never followed.
 */

use App\Models\GalleryCategory;
use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\StoryChapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function duplicateTenant(string $slug): Reseller
{
    Role::findOrCreate('reseller', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => ucfirst($slug).' Funerals',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

function memorialToCopy(Reseller $source): Memorial
{
    $memorial = Memorial::factory()->create([
        'reseller_id' => $source->id,
        'original_reseller_id' => $source->id,
        'user_id' => $source->owner_user_id,
        'slug' => 'jane-doe',
        'visitor_count' => 4210,
    ]);

    // Its own albums, not the three every memorial is seeded with.
    $memorial->galleryCategories()->delete();
    $category = GalleryCategory::create(['memorial_id' => $memorial->id, 'name' => 'Early Years', 'sort_order' => 0]);
    $chapter = StoryChapter::create(['memorial_id' => $memorial->id, 'title' => 'Childhood', 'sort_order' => 0]);

    $memorial->posts()->create([
        'user_id' => $source->owner_user_id,
        'story_chapter_id' => $chapter->id,
        'type' => 'story',
        'content' => 'She grew up in Nazigo.',
        'is_published' => true,
    ]);

    $memorial->media()->create([
        'user_id' => $source->owner_user_id,
        'gallery_category_id' => $category->id,
        'type' => 'image',
        'path' => 'memorials/jane/one.jpg',
        'filename' => 'one.jpg',
    ]);

    return $memorial->fresh();
}

it('copies a memorial onto another tenant and repoints it at its own records', function () {
    $source = duplicateTenant('source-home');
    $target = duplicateTenant('a-plus');
    $memorial = memorialToCopy($source);

    $this->artisan('memorials:duplicate', ['source' => 'jane-doe', '--to' => 'a-plus'])
        ->assertSuccessful();

    $copy = Memorial::where('reseller_id', $target->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->id)->not->toBe($memorial->id)
        // Slugs are unique across the whole install, so the copy cannot reuse it.
        ->and($copy->slug)->not->toBe($memorial->slug)
        // It belongs to the receiving tenant, not to the family who own the original.
        ->and($copy->user_id)->toBe($target->owner_user_id)
        ->and($copy->original_reseller_id)->toBe($target->id)
        // Seen by nobody yet.
        ->and($copy->visitor_count)->toBe(0);

    expect($copy->posts()->count())->toBe(1)
        ->and($copy->media()->count())->toBe(1)
        ->and($copy->storyChapters()->count())->toBe(1);

    // The whole point: the copy's post and photograph must hang off the *copy's* chapter and
    // album. Pointing back at the original's is invisible until the original is edited.
    expect($copy->posts()->first()->story_chapter_id)->toBe($copy->storyChapters()->first()->id)
        ->and($copy->media()->first()->gallery_category_id)->toBe($copy->galleryCategories()->first()->id);
});

it('does not add the starter albums on top of the ones being copied', function () {
    $source = duplicateTenant('source-home');
    duplicateTenant('a-plus');
    memorialToCopy($source);

    $this->artisan('memorials:duplicate', ['source' => 'jane-doe', '--to' => 'a-plus'])->assertSuccessful();

    $copy = Memorial::where('slug', '!=', 'jane-doe')->latest('id')->first();

    // Memorial::created() seeds Childhood, Family & Friends and Milestones. On a copy those
    // are three albums nobody asked for, on top of the one the original had.
    expect($copy->galleryCategories()->pluck('name')->all())->toBe(['Early Years']);
});

it('changes nothing on a dry run', function () {
    $source = duplicateTenant('source-home');
    $target = duplicateTenant('a-plus');
    memorialToCopy($source);

    $this->artisan('memorials:duplicate', ['source' => 'jane-doe', '--to' => 'a-plus', '--dry-run' => true])
        ->assertSuccessful();

    expect(Memorial::where('reseller_id', $target->id)->count())->toBe(0);
});

it('refuses a tenant it cannot name, rather than guessing', function () {
    $source = duplicateTenant('source-home');
    memorialToCopy($source);

    // Live and local ids never correspond, so an id must not resolve a tenant here.
    $this->artisan('memorials:duplicate', ['source' => 'jane-doe', '--to' => '1'])
        ->assertFailed();

    $this->artisan('memorials:duplicate', ['source' => 'jane-doe', '--to' => 'no-such-home'])
        ->assertFailed();
});

it('refuses to copy a memorial onto the tenant that already owns it', function () {
    $source = duplicateTenant('source-home');
    memorialToCopy($source);

    $this->artisan('memorials:duplicate', ['source' => 'jane-doe', '--to' => 'source-home'])
        ->assertFailed();

    expect(Memorial::count())->toBe(1);
});
