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

/**
 * A tribute is a tap and nothing else.
 *
 * The memorial page carried two written things — a tribute and a chapter — in two
 * sub-tabs, and a visitor had to decide which of the two words their sentence was before
 * they could type it. There is one now: a story, optionally marked as a flower, a candle
 * or a prayer. The tap endpoint records the gesture; the tally under the card is the only
 * place it shows.
 */
it('records a tap and leaves nothing in the feed', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs(User::factory()->create())
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower'])
        ->assertOk();

    expect($memorial->tributes()->count())->toBe(1)
        ->and($memorial->posts()->count())->toBe(0);
});

it('ignores a message sent to the tap endpoint rather than hiding words in the tally', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    // An old client might still send one. It must not become a tribute nobody can see:
    // the feed is stories, and this endpoint does not make stories.
    $this->actingAs(User::factory()->create())
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'candle', 'message' => 'He taught me to sail.'])
        ->assertOk();

    expect($memorial->tributes()->first()->message)->toBeNull();
});

it('counts the tap under its card', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    Tribute::create(['memorial_id' => $memorial->id, 'type' => 'flower', 'is_approved' => true]);
    Tribute::create(['memorial_id' => $memorial->id, 'type' => 'candle', 'is_approved' => true]);

    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertViewHas('tributeCounts', fn ($counts) => $counts['total'] === 2 && $counts['flower'] === 1);
});

it('shows the memorial page its stories, marked and unmarked alike, as one feed', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $memorial->posts()->create([
        'type' => 'text',
        'content' => 'A flame for you.',
        'tribute_type' => 'candle',
        'is_published' => true,
    ]);
    $memorial->posts()->create([
        'type' => 'text',
        'content' => 'We met on a Tuesday.',
        'is_published' => true,
    ]);

    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertSee('A flame for you.', false)
        ->assertSee('We met on a Tuesday.', false)
        ->assertSee('lit a candle', false)
        ->assertViewHas('stories', fn ($stories) => $stories->count() === 2)
        ->assertViewHas('storyCounts', fn ($counts) => $counts['total'] === 2 && $counts['candle'] === 1 && $counts['story'] === 1);
});

it('keeps an unpublished story out of the feed', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $memorial->posts()->create([
        'type' => 'text',
        'content' => 'Not ready yet.',
        'is_published' => false,
    ]);

    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertViewHas('stories', fn ($stories) => $stories->count() === 0);
});