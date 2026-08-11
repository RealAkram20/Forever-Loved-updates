<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Support\PlanFeatures;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * The three plans that are actually sold.
     *
     * Values transcribed from the published pricing table. Two things are worth knowing
     * before editing them:
     *
     *  - -1 is unlimited and 0 is none. They used to be the same number, and setting the
     *    free plan's video allowance to 0 granted unlimited video rather than withholding
     *    it. Anything omitted below falls back to the catalogue default.
     *  - The video allowance is measured in megabytes, not hours. Duration cannot be
     *    enforced without ffprobe on the host, and the figure a browser reports is set by
     *    whoever is uploading. 12 GB is roughly ten hours of phone video.
     *
     * firstOrCreate on the slug, so this is idempotent and never overwrites pricing an
     * admin has since changed by hand. Plans seeded previously (the old Free/Premium pair)
     * are left in place — retiring one is a decision for the admin screen, not a seeder,
     * because memorials may still be subscribed to it.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Create and share a memorial.',
                'price' => 0,
                'interval' => 'lifetime',
                'sort_order' => 1,

                'memorial_limit' => 1,
                'max_gallery_images' => 5,
                'max_gallery_videos' => PlanFeatures::NONE,
                'max_video_storage_mb' => PlanFeatures::NONE,
                'storage_limit_mb' => 500,
                'max_contributors' => 5,
                'max_tributes' => PlanFeatures::NONE,
                'max_chapters' => 3,
                'max_ai_bio_per_day' => 0,

                'feature_albums' => false,
                'feature_tributes' => false,
                'feature_background_music' => false,
                'feature_share_memories' => true,
                'feature_advanced_privacy' => true,
                'feature_guest_notifications' => false,
                'feature_no_ads' => false,
                'feature_never_expires' => false,
            ],
            [
                'name' => 'Annual',
                'slug' => 'annual',
                'description' => 'Build a collaborative family tribute.',
                'price' => 79,
                'interval' => 'yearly',
                'sort_order' => 2,
                'is_popular' => true,

                'memorial_limit' => 1,
                'max_gallery_images' => 500,
                'max_gallery_videos' => 100,
                // ~10 hours of phone video.
                'max_video_storage_mb' => 12288,
                'storage_limit_mb' => 20480,
                'max_contributors' => PlanFeatures::UNLIMITED,
                'max_tributes' => PlanFeatures::UNLIMITED,
                'max_chapters' => PlanFeatures::UNLIMITED,
                'max_ai_bio_per_day' => 5,

                'feature_albums' => true,
                'feature_tributes' => true,
                'feature_background_music' => true,
                'feature_share_memories' => true,
                'feature_advanced_privacy' => true,
                'feature_guest_notifications' => true,
                'feature_no_ads' => true,
                // Deliberately false: an annual plan lapses if it is not renewed. Permanent
                // preservation is the whole of what the lifetime plan sells.
                'feature_never_expires' => false,
            ],
            [
                'name' => 'Lifetime',
                'slug' => 'lifetime',
                'description' => 'Preserve that tribute permanently.',
                'price' => 99,
                'interval' => 'lifetime',
                'sort_order' => 3,

                'memorial_limit' => 1,
                'max_gallery_images' => PlanFeatures::UNLIMITED,
                'max_gallery_videos' => PlanFeatures::UNLIMITED,
                'max_video_storage_mb' => PlanFeatures::UNLIMITED,
                'storage_limit_mb' => PlanFeatures::UNLIMITED,
                'max_contributors' => PlanFeatures::UNLIMITED,
                'max_tributes' => PlanFeatures::UNLIMITED,
                'max_chapters' => PlanFeatures::UNLIMITED,
                'max_ai_bio_per_day' => 10,

                'feature_albums' => true,
                'feature_tributes' => true,
                'feature_background_music' => true,
                'feature_share_memories' => true,
                'feature_advanced_privacy' => true,
                'feature_guest_notifications' => true,
                'feature_no_ads' => true,
                'feature_never_expires' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(
                ['slug' => $plan['slug'], 'reseller_id' => null],
                array_merge(['is_active' => true], $plan)
            );
        }
    }
}
