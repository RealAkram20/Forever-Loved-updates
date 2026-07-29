<?php

namespace Database\Factories;

use App\Models\Memorial;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Memorial>
 */
class MemorialFactory extends Factory
{
    protected $model = Memorial::class;

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $fullName = "{$firstName} {$lastName}";

        $birthDate = fake()->dateTimeBetween('-95 years', '-30 years');
        $passingDate = fake()->dateTimeBetween($birthDate, 'now');

        $isFree = fake()->boolean(60);
        $plan = SubscriptionPlan::where('slug', $isFree ? 'free' : 'premium')->first();

        return [
            'user_id' => User::factory(),
            'slug' => $this->uniqueSlug($fullName),
            'title' => "In Loving Memory of {$fullName}",
            'full_name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => fake()->randomElement(['male', 'female']),
            'relationship' => fake()->randomElement(['Mother', 'Father', 'Spouse', 'Grandmother', 'Grandfather', 'Friend', 'Sibling']),
            'short_description' => fake()->sentence(12),
            'nationality' => fake()->country(),
            'primary_profession' => fake()->jobTitle(),
            'known_for' => fake()->catchPhrase(),
            'major_achievements' => fake()->paragraph(2),
            'date_of_birth' => $birthDate,
            'date_of_passing' => $passingDate,
            'birth_city' => fake()->city(),
            'birth_country' => fake()->country(),
            'death_city' => fake()->city(),
            'death_country' => fake()->country(),
            'biography' => fake()->paragraphs(4, true),
            'theme' => fake()->randomElement(['classic', 'modern', 'garden']),
            'plan' => $isFree ? Memorial::PLAN_FREE : Memorial::PLAN_PAID,
            'subscription_plan_id' => $plan?->id,
            'is_public' => fake()->boolean(85),
            'status' => Memorial::STATUS_ACTIVE,
            'completion_status' => fake()->randomElement([Memorial::COMPLETION_PENDING, Memorial::COMPLETION_COMPLETED]),
            'visitor_count' => fake()->numberBetween(0, 500),
        ];
    }

    private function uniqueSlug(string $fullName): string
    {
        $base = Str::slug($fullName);
        $slug = $base;
        $suffix = 1;

        while (Memorial::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
