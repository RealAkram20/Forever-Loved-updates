<?php

namespace Database\Seeders;

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\StoryChapter;
use App\Models\Tribute;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Populates local/staging environments with realistic-looking demo data: admins,
 * resellers (each with an owner + a few staff), independent platform users, and
 * memorials spread across all of them. Not wired into DatabaseSeeder — this is
 * sample content for exploring the admin UI, not something a fresh production
 * install should ever run. Invoke explicitly: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Creating admin users...');
        $admins = User::factory()->count(3)->create([
            'password' => Hash::make('password'),
        ]);
        $admins->each(fn (User $u) => $u->assignRole('admin'));

        $this->command?->info('Creating resellers with owners and staff...');
        $resellers = Reseller::factory()->count(3)->create();

        foreach ($resellers as $reseller) {
            $owner = User::factory()->create([
                'name' => $reseller->name.' Admin',
                'password' => Hash::make('password'),
                'reseller_id' => $reseller->id,
            ]);
            $owner->assignRole('reseller');
            $reseller->update(['owner_user_id' => $owner->id]);

            $staff = User::factory()->count(2)->create([
                'password' => Hash::make('password'),
                'reseller_id' => $reseller->id,
            ]);
            $staff->each(fn (User $u) => $u->assignRole('reseller'));

            // A handful of memorials under this reseller's tenant, owned by its staff.
            foreach ($staff->push($owner) as $staffMember) {
                Memorial::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->forUser($staffMember)
                    ->create(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id])
                    ->each(fn (Memorial $m) => $this->enrichMemorial($m));
            }
        }

        $this->command?->info('Creating independent platform users with memorials...');
        $users = User::factory()->count(15)->create([
            'password' => Hash::make('password'),
        ]);
        $users->each(fn (User $u) => $u->assignRole('user'));

        foreach ($users as $user) {
            Memorial::factory()
                ->count(fake()->numberBetween(0, 2))
                ->forUser($user)
                ->create()
                ->each(fn (Memorial $m) => $this->enrichMemorial($m));
        }

        $this->command?->info('Demo data created.');
    }

    /**
     * One story chapter and a few tributes per memorial, so the admin UI and memorial
     * pages don't look empty when browsing the seeded demo content.
     */
    private function enrichMemorial(Memorial $memorial): void
    {
        StoryChapter::create([
            'memorial_id' => $memorial->id,
            'title' => 'Early Life',
            'description' => fake()->paragraph(3),
            'sort_order' => 0,
        ]);

        $tributeCount = fake()->numberBetween(0, 4);
        for ($i = 0; $i < $tributeCount; $i++) {
            $fromGuest = fake()->boolean(50);

            Tribute::create([
                'memorial_id' => $memorial->id,
                'user_id' => $fromGuest ? null : $memorial->user_id,
                'type' => fake()->randomElement([Tribute::TYPE_FLOWER, Tribute::TYPE_CANDLE, Tribute::TYPE_NOTE]),
                'message' => fake()->sentence(15),
                'guest_name' => $fromGuest ? fake()->name() : null,
                'guest_email' => $fromGuest ? fake()->safeEmail() : null,
                'is_approved' => true,
            ]);
        }
    }
}
