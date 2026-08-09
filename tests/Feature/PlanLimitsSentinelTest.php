<?php

use App\Helpers\PlanLimitsHelper;
use App\Models\Media;
use App\Models\Memorial;
use App\Models\MemorialCollaborator;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PlanFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'reseller', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    Storage::fake('public');
});

/**
 * Zero used to mean unlimited, which made "this plan includes none of that" impossible to
 * express — and failed in the worst direction available: an admin withholding video from the
 * free plan would type 0, and the check read it as no ceiling and granted unlimited video.
 *
 * -1 is now unlimited and 0 is none. These tests exist so that can never quietly invert
 * again, because nothing about the symptom would look like a bug from the admin screen.
 */
function memorialOnPlan(array $planAttributes): Memorial
{
    // create() rather than a factory — there is none for plans, and spelling the columns out
    // means a test says exactly which entitlement it is about.
    $plan = SubscriptionPlan::create(array_merge([
        'name' => 'Test',
        'slug' => 'test-'.substr(uniqid(), -8),
        'price' => 0,
        'interval' => 'lifetime',
        'is_active' => true,
    ], $planAttributes));

    $owner = User::factory()->create();

    return Memorial::factory()->create([
        'user_id' => $owner->id,
        'subscription_plan_id' => $plan->id,
    ]);
}

/** Memorial has no user() relation, so the owner is fetched by key. */
function ownerOf(Memorial $memorial): User
{
    return User::findOrFail($memorial->user_id);
}

it('treats -1 as unlimited', function () {
    $memorial = memorialOnPlan(['max_gallery_images' => PlanFeatures::UNLIMITED]);

    foreach (range(1, 12) as $i) {
        Media::create([
            'memorial_id' => $memorial->id,
            'type' => 'photo',
            'path' => "memorials/{$memorial->id}/gallery/{$i}.jpg",
        ]);
    }

    expect(PlanLimitsHelper::canUploadGalleryImage($memorial->fresh())['allowed'])->toBeTrue();
});

it('treats 0 as none, refusing the very first one', function () {
    $memorial = memorialOnPlan(['max_gallery_videos' => PlanFeatures::NONE]);

    $check = PlanLimitsHelper::canUploadGalleryVideo($memorial);

    // The inversion this whole change exists to fix: this used to return allowed => true.
    expect($check['allowed'])->toBeFalse()
        ->and($check['reason'])->toContain('not included');
});

it('still blocks at the boundary of a real limit', function () {
    $memorial = memorialOnPlan(['max_gallery_images' => 2]);

    expect(PlanLimitsHelper::canUploadGalleryImage($memorial)['allowed'])->toBeTrue();

    foreach (range(1, 2) as $i) {
        Media::create([
            'memorial_id' => $memorial->id,
            'type' => 'photo',
            'path' => "memorials/{$memorial->id}/gallery/{$i}.jpg",
        ]);
    }

    expect(PlanLimitsHelper::canUploadGalleryImage($memorial->fresh())['allowed'])->toBeFalse();
});

it('refuses the upload endpoint when the plan includes no video', function () {
    $memorial = memorialOnPlan(['max_gallery_videos' => PlanFeatures::NONE]);

    test()->actingAs(ownerOf($memorial))
        ->post('http://localhost/m/'.$memorial->slug.'/gallery', [
            'file' => UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
        ])
        ->assertStatus(422);

    expect($memorial->media()->count())->toBe(0);
});

it('caps contributors at the plan\'s number', function () {
    $memorial = memorialOnPlan([
        'max_contributors' => 1,
        'feature_advanced_privacy' => true,
    ]);

    MemorialCollaborator::create([
        'memorial_id' => $memorial->id,
        'user_id' => User::factory()->create()->id,
        'role' => 'editor',
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    test()->actingAs(ownerOf($memorial))
        ->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
            'email' => 'second@example.test',
            'role' => 'editor',
        ])
        ->assertStatus(422);
});

it('does not count an invitation nobody has accepted against the cap', function () {
    $memorial = memorialOnPlan([
        'max_contributors' => 1,
        'feature_advanced_privacy' => true,
    ]);

    MemorialCollaborator::create([
        'memorial_id' => $memorial->id,
        'user_id' => User::factory()->create()->id,
        'role' => 'editor',
        'invited_at' => now(),
        'accepted_at' => null,
    ]);

    expect(PlanLimitsHelper::canAddContributor($memorial)['allowed'])->toBeTrue();
});

it('refuses an upload that would pass the video allowance but allows the same size as a photo', function () {
    $memorial = memorialOnPlan([
        'max_gallery_videos' => PlanFeatures::UNLIMITED,
        'max_gallery_images' => PlanFeatures::UNLIMITED,
        'max_video_storage_mb' => 1,
        'storage_limit_mb' => 500,
    ]);

    // 5 MB of video against a 1 MB video allowance.
    test()->actingAs(ownerOf($memorial))
        ->post('http://localhost/m/'.$memorial->slug.'/gallery', [
            'file' => UploadedFile::fake()->create('clip.mp4', 5120, 'video/mp4'),
        ])
        ->assertStatus(422);

    // The same weight as a photo is fine — the video budget is video's alone.
    test()->actingAs(ownerOf($memorial))
        ->post('http://localhost/m/'.$memorial->slug.'/gallery', [
            'file' => UploadedFile::fake()->create('portrait.jpg', 5120, 'image/jpeg'),
        ])
        ->assertOk();
});

it('enforces the overall storage allowance, which nothing used to read', function () {
    $memorial = memorialOnPlan([
        'max_gallery_images' => PlanFeatures::UNLIMITED,
        'storage_limit_mb' => 1,
    ]);

    test()->actingAs(ownerOf($memorial))
        ->post('http://localhost/m/'.$memorial->slug.'/gallery', [
            'file' => UploadedFile::fake()->create('big.jpg', 4096, 'image/jpeg'),
        ])
        ->assertStatus(422);
});

it('hides tributes entirely when the plan does not include them', function () {
    $memorial = memorialOnPlan([
        'feature_tributes' => false,
        'max_tributes' => PlanFeatures::UNLIMITED,
    ]);

    // Unlimited *count* must not be enough on its own — the flag is asked first.
    expect(PlanLimitsHelper::canAddTribute($memorial)['allowed'])->toBeFalse();
});

it('gates album creation on the plan', function () {
    $memorial = memorialOnPlan(['feature_albums' => false]);

    test()->actingAs(ownerOf($memorial))
        ->postJson('http://localhost/m/'.$memorial->slug.'/gallery-categories', ['name' => 'School Life'])
        ->assertStatus(403);

    $memorial->subscriptionPlan->update(['feature_albums' => true]);

    test()->actingAs(ownerOf($memorial))
        ->postJson('http://localhost/m/'.$memorial->slug.'/gallery-categories', ['name' => 'School Life'])
        ->assertStatus(201);
});

it('keeps the seeded tiers matching the published pricing', function () {
    $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

    $free = SubscriptionPlan::where('slug', 'free')->whereNull('reseller_id')->first();
    $annual = SubscriptionPlan::where('slug', 'annual')->whereNull('reseller_id')->first();
    $lifetime = SubscriptionPlan::where('slug', 'lifetime')->whereNull('reseller_id')->first();

    expect((float) $free->price)->toBe(0.0)
        ->and($free->max_gallery_images)->toBe(5)
        ->and($free->max_gallery_videos)->toBe(PlanFeatures::NONE)
        ->and($free->max_contributors)->toBe(5)
        ->and($free->feature_tributes)->toBeFalse()
        ->and($free->feature_never_expires)->toBeFalse();

    expect((float) $annual->price)->toBe(79.0)
        ->and($annual->interval)->toBe('yearly')
        ->and($annual->max_gallery_images)->toBe(500)
        ->and($annual->max_contributors)->toBe(PlanFeatures::UNLIMITED)
        ->and($annual->feature_albums)->toBeTrue()
        // The one row that separates it from Lifetime.
        ->and($annual->feature_never_expires)->toBeFalse();

    expect((float) $lifetime->price)->toBe(99.0)
        ->and($lifetime->interval)->toBe('lifetime')
        ->and($lifetime->max_gallery_images)->toBe(PlanFeatures::UNLIMITED)
        ->and($lifetime->feature_never_expires)->toBeTrue();
});

it('keeps unbuilt features out of what a customer is shown', function () {
    $public = array_keys(PlanFeatures::publicRows());

    expect($public)->not->toContain('feature_qr_code')
        ->and($public)->not->toContain('feature_timeline')
        ->and($public)->not->toContain('feature_order_of_service')
        // ...while the admin form still offers every one of them.
        ->and(PlanFeatures::columns())->toContain('feature_qr_code')
        ->and(PlanFeatures::columns())->toContain('feature_timeline');
});
