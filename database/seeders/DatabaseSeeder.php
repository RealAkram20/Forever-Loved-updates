<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Admin user is created by the web installer (InstallController).
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(ResellerTierSeeder::class);
        $this->call(SubscriptionPlanSeeder::class);
        $this->call(SiteLayoutSeeder::class);
        $this->call(PageSeeder::class);
        $this->call(MarketingPagesSeeder::class);
        $this->call(DefaultMenusSeeder::class);
    }
}
