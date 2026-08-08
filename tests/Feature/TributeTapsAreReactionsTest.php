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
 * A tap on one of the one-tap cards is a like, not a post. It counts under the card and
 * leaves nothing in the feed; only tributes somebody wrote words on are listed.
 */
it('keeps a bare tap out of the feed while still counting it', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs(User::factory()->create())
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower'])
        ->assertOk();

    // Stored, and counted.
    expect($memorial->tributes()->count())->toBe(1);
    // But not part of the feed.
    expect($memorial->tributes()->withMessage()->count())->toBe(0);
});

it('lists a tribute somebody wrote words on', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs(User::factory()->create())
        ->postJson("/m/{$memorial->slug}/tribute", [
            'type' => 'candle',
            'message' => 'He taught me to sail.',
        ])
        ->assertOk();

    expect($memorial->tributes()->withMessage()->count())->toBe(1);
});

it('does not count an untouched rich text editor as words', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    // What Quill submits when the composer is opened and nothing is typed. Without this
    // being treated as empty, opening the form would be enough to post.
    Tribute::create([
        'memorial_id' => $memorial->id,
        'type' => 'prayer',
        'message' => '<p><br></p>',
        'is_approved' => true,
    ]);

    expect($memorial->tributes()->withMessage()->count())->toBe(0);
});

it('promotes a tap to a post when words are added to it afterwards', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs($visitor)->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower'])->assertOk();
    expect($memorial->tributes()->withMessage()->count())->toBe(0);

    // The page has no entry to update — reactions are not listed — so it has to be told
    // that this one has just become a post.
    $this->actingAs($visitor)
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower', 'message' => 'For every summer.'])
        ->assertOk()
        ->assertJson(['duplicate' => true, 'promoted' => true]);

    expect($memorial->tributes()->count())->toBe(1);
    expect($memorial->tributes()->withMessage()->count())->toBe(1);
});

it('does not report a plain repeat tap as promoted', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs($visitor)->postJson("/m/{$memorial->slug}/tribute", ['type' => 'candle'])->assertOk();

    $this->actingAs($visitor)
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'candle'])
        ->assertOk()
        ->assertJson(['duplicate' => true, 'promoted' => false]);
});

it('shows the memorial page the written tributes only', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    Tribute::create([
        'memorial_id' => $memorial->id,
        'type' => 'flower',
        'message' => null,
        'is_approved' => true,
    ]);
    Tribute::create([
        'memorial_id' => $memorial->id,
        'type' => 'candle',
        'message' => 'A flame for you.',
        'is_approved' => true,
    ]);

    $this->get("/{$memorial->slug}")
        ->assertOk()
        ->assertSee('A flame for you.', false)
        // The card tally counts both; the feed lists one.
        ->assertViewHas('tributeCounts', fn ($counts) => $counts['total'] === 2)
        ->assertViewHas('tributeWrittenCounts', fn ($counts) => $counts['total'] === 1)
        ->assertViewHas('tributes', fn ($tributes) => $tributes->total() === 1);
});
