<?php

namespace Database\Factories;

use App\Models\Reseller;
use App\Models\ResellerTier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reseller>
 */
class ResellerFactory extends Factory
{
    protected $model = Reseller::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'reseller_tier_id' => ResellerTier::inRandomOrder()->value('id'),
            'status' => Reseller::STATUS_ACTIVE,
            'primary_color' => fake()->hexColor(),
            'pesapal_enabled' => false,
            'pesapal_environment' => 'sandbox',
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Reseller::where('slug', $slug)->exists() || in_array($slug, Reseller::reservedSlugs(), true)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
