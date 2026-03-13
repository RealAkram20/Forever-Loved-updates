<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed the subscription plans.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Basic memorial with limited features',
                'price' => 0,
                'interval' => 'lifetime',
                'memorial_limit' => 1,
                'storage_limit_mb' => 100,
                'max_gallery_images' => 10,
                'max_gallery_videos' => 2,
                'max_tributes' => 20,
                'max_chapters' => 3,
                'max_ai_bio_per_day' => 0,
                'feature_background_music' => false,
                'feature_advanced_privacy' => false,
                'feature_guest_notifications' => false,
                'feature_never_expires' => false,
                'feature_no_ads' => false,
                'feature_share_memories' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Enhanced memorial with premium themes and more storage',
                'price' => 9.99,
                'interval' => 'monthly',
                'memorial_limit' => 5,
                'storage_limit_mb' => 1024,
                'max_gallery_images' => 0,
                'max_gallery_videos' => 0,
                'max_tributes' => 0,
                'max_chapters' => 0,
                'max_ai_bio_per_day' => 5,
                'feature_background_music' => true,
                'feature_advanced_privacy' => true,
                'feature_guest_notifications' => true,
                'feature_never_expires' => true,
                'feature_no_ads' => true,
                'feature_share_memories' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
