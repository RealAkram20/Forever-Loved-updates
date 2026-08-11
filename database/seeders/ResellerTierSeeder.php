<?php

namespace Database\Seeders;

use App\Models\ResellerTier;
use Illuminate\Database\Seeder;

class ResellerTierSeeder extends Seeder
{
    /**
     * Seed the four B2B reseller tiers. Storage limits and the domain-routing/business-analytics
     * flags were left unspecified in the source pricing sheet — seeded null/false, admin-editable
     * from Settings → Resellers → Tiers.
     */
    public function run(): void
    {
        $tiers = [
            ['name' => 'Starter', 'slug' => 'starter', 'sort_order' => 1, 'annual_price' => 16250, 'memorial_profile_allowance' => 250, 'price_per_additional_profile' => 65, 'feature_embedding' => false, 'feature_page_builder' => false],
            ['name' => 'Growth', 'slug' => 'growth', 'sort_order' => 2, 'annual_price' => 29000, 'memorial_profile_allowance' => 500, 'price_per_additional_profile' => 58, 'feature_embedding' => true, 'feature_page_builder' => true],
            ['name' => 'Professional', 'slug' => 'professional', 'sort_order' => 3, 'annual_price' => 37500, 'memorial_profile_allowance' => 750, 'price_per_additional_profile' => 50, 'feature_embedding' => true, 'feature_page_builder' => true],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'sort_order' => 4, 'annual_price' => 45000, 'memorial_profile_allowance' => null, 'price_per_additional_profile' => 45, 'feature_embedding' => true, 'feature_page_builder' => true],
        ];

        foreach ($tiers as $tier) {
            ResellerTier::firstOrCreate(['slug' => $tier['slug']], $tier);
        }
    }
}
