<?php

use App\Helpers\PlanLimitsHelper;
use App\Models\GalleryCategory;
use App\Models\Media;
use App\Models\Memorial;
use App\Models\MemorialCollaborator;
use App\Models\Post;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Memorial::canBeEditedBy() asks hasRole() on nearly every path, and Spatie throws
    // rather than returning false when the role has never been defined — so an unseeded
    // test dies with an exception instead of the 403 it is asserting.
    foreach (['admin', 'super-admin', 'reseller', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    Storage::fake('public');

    // Albums are a paid feature, and a memorial with no plan of its own falls back to the
    // free one. These tests are about who may curate a category and how the model behaves,
    // not about the plan gate — that is covered in PlanLimitsSentinelTest — so the fallback
    // plan here includes albums and real, countable allowances.
    SubscriptionPlan::create([
        'name' => 'Test Free',
        'slug' => 'free',
        'price' => 0,
        'interval' => 'lifetime',
        'is_active' => true,
        'feature_albums' => true,
        'max_gallery_images' => 100,
        'max_gallery_videos' => 100,
        'storage_limit_mb' => 5000,
        'max_video_storage_mb' => 5000,
    ]);
});

function categoriesUrl(Memorial $memorial): string
{
    return 'http://localhost/m/'.$memorial->slug.'/gallery-categories';
}

function galleryUrl(Memorial $memorial): string
{
    return 'http://localhost/m/'.$memorial->slug.'/gallery';
}

function anEditorOf(Memorial $memorial): User
{
    $editor = User::factory()->create();

    // All three matter: the role, the acceptance, and the user id. An invited-but-unaccepted
    // collaborator is deliberately not an editor yet.
    MemorialCollaborator::create([
        'memorial_id' => $memorial->id,
        'user_id' => $editor->id,
        'role' => 'editor',
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    return $editor;
}

function aStoryPhotoOn(Memorial $memorial): Media
{
    $media = Media::create([
        'memorial_id' => $memorial->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/posts/story.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $post = Post::create([
        'memorial_id' => $memorial->id,
        'type' => 'gallery',
        'content' => 'She taught me to swim.',
    ]);
    $post->media()->attach($media->id, ['sort_order' => 0]);

    return $media;
}

it('gives every new memorial the three starting categories', function () {
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    expect($memorial->galleryCategories()->pluck('name')->all())
        ->toBe(['Childhood', 'Family & Friends', 'Milestones']);
});

it('lets the owner create a category', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    test()->actingAs($owner)
        ->postJson(categoriesUrl($memorial), ['name' => 'School Life'])
        ->assertStatus(201)
        ->assertJson(['success' => true, 'category' => ['name' => 'School Life', 'slug' => 'school-life']]);

    expect($memorial->galleryCategories()->where('name', 'School Life')->exists())->toBeTrue();
});

it('lets an accepted editor collaborator create a category', function () {
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);
    $editor = anEditorOf($memorial);

    test()->actingAs($editor)
        ->postJson(categoriesUrl($memorial), ['name' => 'The Farm'])
        ->assertStatus(201);
});

it('lets a platform admin create a category on any memorial', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    test()->actingAs($admin)
        ->postJson(categoriesUrl($memorial), ['name' => 'Service Years'])
        ->assertStatus(201);
});

it('refuses a stranger and a guest', function () {
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);
    $stranger = User::factory()->create();
    $category = $memorial->galleryCategories()->first();

    test()->actingAs($stranger)->postJson(categoriesUrl($memorial), ['name' => 'Mine now'])->assertStatus(403);
    test()->actingAs($stranger)->patchJson(categoriesUrl($memorial).'/'.$category->id, ['name' => 'Mine now'])->assertStatus(403);
    test()->actingAs($stranger)->deleteJson(categoriesUrl($memorial).'/'.$category->id)->assertStatus(403);

    test()->postJson(categoriesUrl($memorial), ['name' => 'Mine now'])->assertStatus(403);

    expect($memorial->galleryCategories()->count())->toBe(3);
});

it('rejects a duplicate name whatever its casing', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    test()->actingAs($owner)
        ->postJson(categoriesUrl($memorial), ['name' => 'childhood'])
        ->assertStatus(422);

    expect($memorial->galleryCategories()->count())->toBe(3);
});

it('caps the number of categories', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    for ($i = $memorial->galleryCategories()->count(); $i < GalleryCategory::MAX_PER_MEMORIAL; $i++) {
        test()->actingAs($owner)->postJson(categoriesUrl($memorial), ['name' => 'Category '.$i])->assertStatus(201);
    }

    test()->actingAs($owner)
        ->postJson(categoriesUrl($memorial), ['name' => 'One too many'])
        ->assertStatus(422);

    expect($memorial->galleryCategories()->count())->toBe(GalleryCategory::MAX_PER_MEMORIAL);
});

it('renames a category without moving its photos', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);
    $category = $memorial->galleryCategories()->first();

    $media = Media::create([
        'memorial_id' => $memorial->id,
        'gallery_category_id' => $category->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/a.jpg',
    ]);

    test()->actingAs($owner)
        ->patchJson(categoriesUrl($memorial).'/'.$category->id, ['name' => 'The Early Years'])
        ->assertOk()
        ->assertJson(['category' => ['name' => 'The Early Years', 'slug' => $category->slug]]);

    expect($media->fresh()->gallery_category_id)->toBe($category->id);
});

it('releases photos instead of deleting them when a category goes', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);
    $category = $memorial->galleryCategories()->first();

    Storage::disk('public')->put('memorials/'.$memorial->id.'/gallery/a.jpg', 'x');
    $media = Media::create([
        'memorial_id' => $memorial->id,
        'gallery_category_id' => $category->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/a.jpg',
    ]);

    test()->actingAs($owner)
        ->deleteJson(categoriesUrl($memorial).'/'.$category->id)
        ->assertOk()
        ->assertJson(['success' => true, 'unfiled_count' => 1]);

    expect(Media::find($media->id))->not->toBeNull()
        ->and($media->fresh()->gallery_category_id)->toBeNull();

    Storage::disk('public')->assertExists($media->path);
});

it('files an upload into the chosen category', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);
    $category = $memorial->galleryCategories()->first();

    test()->actingAs($owner)
        ->post(galleryUrl($memorial), [
            'file' => UploadedFile::fake()->image('school.jpg'),
            'gallery_category_id' => $category->id,
        ])
        ->assertOk()
        ->assertJson(['media' => ['gallery_category_id' => $category->id]]);
});

it('will not file media into another memorial\'s category', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);
    $elsewhere = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);
    $foreign = $elsewhere->galleryCategories()->first();

    test()->actingAs($owner)
        ->post(galleryUrl($memorial), [
            'file' => UploadedFile::fake()->image('a.jpg'),
            'gallery_category_id' => $foreign->id,
        ])
        ->assertOk()
        ->assertJson(['media' => ['gallery_category_id' => null]]);

    $media = Media::create([
        'memorial_id' => $memorial->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/b.jpg',
    ]);

    test()->actingAs($owner)
        ->patchJson(galleryUrl($memorial).'/'.$media->id, ['gallery_category_id' => $foreign->id])
        ->assertOk();

    expect($media->fresh()->gallery_category_id)->toBeNull();
});

it('moves media between categories and un-files it on an explicit null', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);
    [$first, $second] = $memorial->galleryCategories()->take(2)->get()->all();

    $media = Media::create([
        'memorial_id' => $memorial->id,
        'gallery_category_id' => $first->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/c.jpg',
    ]);

    test()->actingAs($owner)
        ->patchJson(galleryUrl($memorial).'/'.$media->id, ['gallery_category_id' => $second->id])
        ->assertOk();
    expect($media->fresh()->gallery_category_id)->toBe($second->id);

    test()->actingAs($owner)
        ->patchJson(galleryUrl($memorial).'/'.$media->id, ['gallery_category_id' => null])
        ->assertOk();
    expect($media->fresh()->gallery_category_id)->toBeNull();
});

it('leaves the category alone when only the caption is sent', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);
    $category = $memorial->galleryCategories()->first();

    $media = Media::create([
        'memorial_id' => $memorial->id,
        'gallery_category_id' => $category->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/d.jpg',
    ]);

    test()->actingAs($owner)
        ->patchJson(galleryUrl($memorial).'/'.$media->id, ['caption' => 'Sports day, 1974'])
        ->assertOk();

    expect($media->fresh()->gallery_category_id)->toBe($category->id)
        ->and($media->fresh()->caption)->toBe('Sports day, 1974');
});

it('separates story media from gallery uploads', function () {
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    $storyPhoto = aStoryPhotoOn($memorial);
    $upload = Media::create([
        'memorial_id' => $memorial->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/e.jpg',
    ]);
    // Music never belongs to either set — it is background audio, not something to browse.
    Media::create([
        'memorial_id' => $memorial->id,
        'type' => 'music',
        'path' => 'memorials/'.$memorial->id.'/posts/song.mp3',
    ]);

    expect($memorial->storyMedia()->pluck('id')->all())->toBe([$storyPhoto->id])
        ->and($memorial->galleryMedia()->pluck('id')->all())->toBe([$upload->id]);
});

it('counts only uploads against the gallery quota, never story attachments', function () {
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    aStoryPhotoOn($memorial);
    Media::create([
        'memorial_id' => $memorial->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/g.jpg',
    ]);
    Media::create([
        'memorial_id' => $memorial->id,
        'type' => 'video',
        'path' => 'memorials/'.$memorial->id.'/gallery/h.mp4',
    ]);

    // Surfacing story media in the gallery grid must not start charging families for
    // pictures other people gave them.
    expect(PlanLimitsHelper::canUploadGalleryImage($memorial)['current'])->toBe(1)
        ->and(PlanLimitsHelper::canUploadGalleryVideo($memorial)['current'])->toBe(1);
});

it('does not let another memorial\'s story media change this one\'s quota', function () {
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);
    $elsewhere = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    aStoryPhotoOn($elsewhere);
    Media::create([
        'memorial_id' => $memorial->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/i.jpg',
    ]);

    expect(PlanLimitsHelper::canUploadGalleryImage($memorial)['current'])->toBe(1);
});

it('renders the chip row with the story category and hides empty ones from visitors', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id, 'is_public' => true]);
    $childhood = $memorial->galleryCategories()->where('name', 'Childhood')->first();

    aStoryPhotoOn($memorial);
    Media::create([
        'memorial_id' => $memorial->id,
        'gallery_category_id' => $childhood->id,
        'type' => 'photo',
        'path' => 'memorials/'.$memorial->id.'/gallery/f.jpg',
    ]);

    // A visitor sees the two categories that hold something, and neither the two empty
    // ones nor the curator's controls.
    $visitor = test()->get('http://localhost/'.$memorial->slug)->assertOk();
    $visitor->assertSee('Childhood')
        ->assertSee('From Stories')
        ->assertDontSee('Milestones')
        ->assertDontSee('data-category-manage', false);

    // The owner sees every category, empty or not, so there is somewhere to file into.
    test()->actingAs($owner)->get('http://localhost/'.$memorial->slug)
        ->assertOk()
        ->assertSee('Milestones')
        ->assertSee('data-category-manage', false);
});

it('refuses to delete a photo a story is still showing', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);
    $storyPhoto = aStoryPhotoOn($memorial);

    Storage::disk('public')->put($storyPhoto->path, 'x');

    test()->actingAs($owner)
        ->deleteJson(galleryUrl($memorial).'/'.$storyPhoto->id)
        ->assertStatus(422);

    expect(Media::find($storyPhoto->id))->not->toBeNull();
    Storage::disk('public')->assertExists($storyPhoto->path);
});
