<?php

use App\Models\Memorial;
use App\Models\Tribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('counts one tribute per person per type however many times they tap', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (range(1, 5) as $tap) {
        $res = $this->actingAs($visitor)
            ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower'])
            ->assertOk();

        // Every tap succeeds — the page needs that to keep playing its burst — but only
        // the first one is a new tribute.
        $res->assertJson(['success' => true, 'duplicate' => $tap > 1]);
    }

    expect($memorial->tributes()->where('type', 'flower')->count())->toBe(1);
});

it('still lets one person leave each different kind', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (Tribute::TYPES as $type) {
        $this->actingAs($visitor)
            ->postJson("/m/{$memorial->slug}/tribute", ['type' => $type])
            ->assertOk()
            ->assertJson(['duplicate' => false]);
    }

    expect($memorial->tributes()->count())->toBe(count(Tribute::TYPES));
});

it('keeps different people counting separately', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (range(1, 3) as $i) {
        $this->actingAs(User::factory()->create())
            ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'candle'])
            ->assertOk()
            ->assertJson(['duplicate' => false]);
    }

    expect($memorial->tributes()->where('type', 'candle')->count())->toBe(3);
});

/**
 * Writing is not capped this way, and must not be: a person may say as many things about
 * someone as they have to say. Only the gesture is one-per-person — that is what makes
 * "12 candles" mean twelve people.
 */
it('lets one person write as many stories as they like, marked the same way', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (['The first thing I said.', 'And another, a week later.'] as $words) {
        $this->actingAs($visitor)
            ->postJson("/m/{$memorial->slug}/tribute-post", [
                'content' => $words,
                'tribute_type' => 'prayer',
            ])
            ->assertOk();
    }

    expect($memorial->posts()->where('tribute_type', 'prayer')->count())->toBe(2);
});

it('counts a guest once across repeat taps', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    // The first post creates an account for the address; the second must recognise them.
    $this->postJson("/m/{$memorial->slug}/tribute", [
        'type' => 'flower',
        'guest_name' => 'Visitor',
        'guest_email' => 'visitor@example.com',
    ])->assertOk()->assertJson(['duplicate' => false]);

    $this->postJson("/m/{$memorial->slug}/tribute", [
        'type' => 'flower',
        'guest_name' => 'Visitor',
        'guest_email' => 'visitor@example.com',
    ])->assertStatus(422)->assertJson(['requires_login' => true]);

    expect($memorial->tributes()->count())->toBe(1);
});
