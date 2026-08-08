<?php

use App\Helpers\StorageHelper;
use App\Models\Media;
use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function resellerWithStorage(?int $limitGb): Reseller
{
    Role::findOrCreate('reseller', 'web');

    $tier = ResellerTier::create([
        'name' => 'Starter', 'slug' => 'starter-'.uniqid(), 'sort_order' => 0,
        'annual_price' => 199, 'memorial_profile_allowance' => null,
        'price_per_additional_profile' => 0, 'storage_limit_gb' => $limitGb,
        'feature_embedding' => false, 'feature_domain_routing' => false,
        'feature_business_analytics' => false, 'is_active' => true,
    ]);

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Acme Funeral Home', 'slug' => 'acme-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id, 'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id]);

    return $reseller;
}

it('counts gallery media and profile photos toward reseller storage', function () {
    $reseller = resellerWithStorage(1);

    $a = Memorial::factory()->create(['reseller_id' => $reseller->id, 'profile_photo_size' => 2_000_000]);
    $b = Memorial::factory()->create(['reseller_id' => $reseller->id, 'profile_photo_size' => null]);

    Media::create(['memorial_id' => $a->id, 'path' => 'a/1.jpg', 'type' => 'photo', 'size' => 5_000_000]);
    Media::create(['memorial_id' => $b->id, 'path' => 'b/1.jpg', 'type' => 'photo', 'size' => 3_000_000]);

    // A memorial belonging to nobody must not be counted against this reseller.
    $orphan = Memorial::factory()->create(['reseller_id' => null, 'profile_photo_size' => 9_000_000]);
    Media::create(['memorial_id' => $orphan->id, 'path' => 'c/1.jpg', 'type' => 'photo', 'size' => 9_000_000]);

    expect($reseller->storageUsedBytes())->toBe(10_000_000)
        ->and($a->storageBytes())->toBe(7_000_000)
        ->and($b->storageBytes())->toBe(3_000_000);
});

it('counts cover banners toward reseller storage', function () {
    $reseller = resellerWithStorage(1);

    // A cover has no media row of its own, so it is invisible to any sum that only reads
    // the media table — the same trap profile photos fell into.
    $a = Memorial::factory()->create([
        'reseller_id' => $reseller->id,
        'profile_photo_size' => 2_000_000,
        'cover_photo_size' => 4_000_000,
    ]);
    $b = Memorial::factory()->create(['reseller_id' => $reseller->id, 'cover_photo_size' => null]);

    Media::create(['memorial_id' => $a->id, 'path' => 'a/1.jpg', 'type' => 'photo', 'size' => 1_000_000]);

    expect($a->storageBytes())->toBe(7_000_000)
        ->and($b->storageBytes())->toBe(0)
        ->and($reseller->storageUsedBytes())->toBe(7_000_000);
});

it('reports a percentage only when the tier caps storage', function () {
    $capped = resellerWithStorage(1);
    Memorial::factory()->create(['reseller_id' => $capped->id, 'profile_photo_size' => 1024 ** 3 / 4]);

    expect($capped->storageLimitBytes())->toBe(1024 ** 3)
        ->and($capped->storagePercentUsed())->toBe(25);

    $uncapped = resellerWithStorage(null);
    expect($uncapped->storageLimitBytes())->toBeNull()
        ->and($uncapped->storagePercentUsed())->toBeNull();
});

it('never reports over 100 percent even when a reseller is past the cap', function () {
    $reseller = resellerWithStorage(1);
    Memorial::factory()->create(['reseller_id' => $reseller->id, 'profile_photo_size' => 3 * 1024 ** 3]);

    expect($reseller->storagePercentUsed())->toBe(100);
});

it('formats bytes in binary units, matching how the limit is converted', function () {
    expect(StorageHelper::formatBytes(0))->toBe('0 B')
        ->and(StorageHelper::formatBytes(512))->toBe('512 B')
        ->and(StorageHelper::formatBytes(2048))->toBe('2 KB')
        ->and(StorageHelper::formatBytes(1024 ** 2))->toBe('1 MB')
        ->and(StorageHelper::formatBytes(1024 ** 3))->toBe('1 GB')
        ->and(StorageHelper::formatBytes((int) (1.5 * 1024 ** 3)))->toBe('1.5 GB')
        ->and(StorageHelper::formatBytes(null))->toBe('0 B');

    // A 1GB tier limit must render as exactly "1 GB", or a reseller at their cap would
    // appear to still have headroom.
    expect(StorageHelper::formatBytes(resellerWithStorage(1)->storageLimitBytes()))->toBe('1 GB');
});
