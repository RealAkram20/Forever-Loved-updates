<?php

use App\Models\Memorial;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * A memorial on a plan that includes background music, viewed by the person who owns it.
 *
 * This combination had no test, and the floating dock broke exactly and only inside it:
 * the music upload control is owner-and-plan-gated, and it reads a variable that a `@php`
 * block further down the template defines. Reordering the dock put the control above its
 * own variable, so every other visitor saw the page fine and the owner got a 500.
 */
function musicPlanMemorial(User $owner): Memorial
{
    $owner->assignRole('user');

    $plan = SubscriptionPlan::create([
        'name' => 'Premium', 'slug' => 'premium-'.uniqid(), 'price' => 0, 'is_active' => true,
        'feature_background_music' => true,
    ]);

    return Memorial::factory()->create([
        'is_public' => true,
        'user_id' => $owner->id,
        'subscription_plan_id' => $plan->id,
    ]);
}

it('renders the memorial for its owner when the plan includes background music', function () {
    $owner = User::factory()->create();
    $memorial = musicPlanMemorial($owner);

    $this->actingAs($owner)
        ->get("/{$memorial->slug}")
        ->assertOk()
        ->assertSee('memorial-dock', false)
        // The control that reads $bgMusicUrl — the one that used to be rendered before it existed.
        ->assertSee('bg-music-admin', false)
        ->assertSee('Add Music', false);
});

it('renders the same memorial for a visitor, without the owner-only control', function () {
    $memorial = musicPlanMemorial(User::factory()->create());

    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertSee('memorial-dock', false)
        ->assertDontSee('bg-music-admin', false);
});

it('keeps the dock off a plan with neither music nor sharing', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    // The dock still renders — the music player lives in it whether or not there is music,
    // and Alpine hides it. What must not appear is a control the plan does not include.
    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertDontSee('bg-music-admin', false);
});