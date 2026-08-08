<?php

use App\Helpers\PlanLimitsHelper;
use App\Models\Memorial;
use App\Models\Tribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function fillTributeQuota(Memorial $memorial): int
{
    $max = PlanLimitsHelper::canAddTribute($memorial)['max'];

    foreach (range(1, $max) as $i) {
        Tribute::create([
            'memorial_id' => $memorial->id,
            'type' => 'flower',
            'message' => "Memory number {$i}.",
            'is_approved' => true,
        ]);
    }

    return $max;
}

/**
 * Reaching the plan's limit used to replace the one-tap cards with a red notice, which
 * took the memorial's warmest gesture away from every visitor because the owner's plan
 * filled up. The gesture stays; only the recording stops.
 */
it('keeps the one-tap cards on the page once the tribute limit is reached', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);
    fillTributeQuota($memorial);

    expect(PlanLimitsHelper::canAddTribute($memorial)['allowed'])->toBeFalse();

    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertSee('data-tribute-action="flower"', false)
        ->assertSee('data-tribute-action="candle"', false)
        ->assertSee('data-tribute-action="prayer"', false);
});

it('marks the card grid so a tap past the limit is never sent', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);
    fillTributeQuota($memorial);

    // The flag the click handler reads to decide it should play the effect and stop there.
    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertSee('data-tribute-quota-reached', false)
        ->assertSee('no longer counted', false);
});

it('leaves the grid unflagged while there is room left', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    Tribute::create([
        'memorial_id' => $memorial->id,
        'type' => 'candle',
        'message' => 'One flame.',
        'is_approved' => true,
    ]);

    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertSee('data-tribute-action="candle"', false)
        ->assertDontSee('data-tribute-quota-reached', false)
        ->assertSee('tributes used', false);
});
