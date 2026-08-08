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

it('attaches a later message to the tribute the person already left', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs($visitor)->postJson("/m/{$memorial->slug}/tribute", ['type' => 'prayer'])->assertOk();

    $this->actingAs($visitor)
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'prayer', 'message' => 'Rest well, friend.'])
        ->assertOk()
        ->assertJson(['duplicate' => true]);

    // The words survive rather than being dropped as a duplicate, and no second row appears.
    expect($memorial->tributes()->count())->toBe(1)
        ->and($memorial->tributes()->first()->message)->toContain('Rest well, friend.');
});

it('does not overwrite a message the person already wrote', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs($visitor)
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'prayer', 'message' => 'The first thing I said.'])
        ->assertOk();

    $this->actingAs($visitor)
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'prayer', 'message' => 'Something else entirely.'])
        ->assertOk();

    expect($memorial->tributes()->first()->message)->toContain('The first thing I said.');
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
