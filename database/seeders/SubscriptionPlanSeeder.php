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
