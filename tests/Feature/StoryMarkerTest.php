<?php

use App\Models\Memorial;
use App\Models\Tribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * The marker: a story is a story, and whoever writes it may say it was a flower, a candle
 * or a prayer. Saying nothing is the common case and has to stay free.
 */
it('stores the marker a visitor chose', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs(User::factory()->create())
        ->postJson("/m/{$memorial->slug}/tribute-post", [
            'content' => 'One flame, every year.',
            'tribute_type' => 'candle',
        ])
        ->assertOk()
        ->assertJson(['post' => ['tribute_type' => 'candle', 'marker_verb' => 'lit a candle']]);

    expect($memorial->posts()->first()->tribute_type)->toBe('candle');
});

it('leaves an unmarked story unmarked', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs(User::factory()->create())
        ->postJson("/m/{$memorial->slug}/tribute-post", ['content' => 'We met on a Tuesday.'])
        ->assertOk()
        ->assertJson(['post' => ['tribute_type' => null, 'marker_verb' => null]]);
});

it('refuses a marker that is not one of the three gestures', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->actingAs(User::factory()->create())
        ->postJson("/m/{$memorial->slug}/tribute-post", [
            'content' => 'Something.',
            'tribute_type' => 'balloon',
        ])
        ->assertStatus(422);
});

it('lets an editor change the marker on a story, and take it off again', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true, 'user_id' => $owner->id]);
    $post = $memorial->posts()->create(['type' => 'text', 'content' => 'Words.', 'is_published' => true]);

    $this->actingAs($owner)
        ->patchJson("/m/{$memorial->slug}/posts/{$post->id}", ['tribute_type' => 'flower'])
        ->assertOk();
    expect($post->fresh()->tribute_type)->toBe('flower');

    $this->actingAs($owner)
        ->patchJson("/m/{$memorial->slug}/posts/{$post->id}", ['tribute_type' => null])
        ->assertOk();
    expect($post->fresh()->tribute_type)->toBeNull();
});

/**
 * The migration's promise: the words people wrote as tributes are stories now, and every
 * link anyone has already sent still reaches them.
 *
 * @see database/migrations/2026_08_08_100001_move_written_tributes_into_stories.php
 */
it('keeps a shared tribute link pointing at the writing it was sent for', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    // A tribute as it was written before the migration ran, and the story the migration
    // made from it — matched, as they are in production, by a shared share id.
    $tribute = Tribute::create([
        'memorial_id' => $memorial->id,
        'type' => 'candle',
        'message' => 'A flame for you.',
        'is_approved' => true,
    ]);
    $story = $memorial->posts()->create([
        'share_id' => $tribute->share_id,
        'type' => 'text',
        'tribute_type' => 'candle',
        'content' => 'A flame for you.',
        'is_published' => true,
    ]);
    DB::table('tributes')->where('id', $tribute->id)->update(['migrated_post_id' => $story->id]);

    $this->get("/{$memorial->slug}/tribute/{$tribute->share_id}")
        ->assertRedirect("/{$memorial->slug}/chapter/{$story->share_id}");

    $this->get("/{$memorial->slug}/chapter/{$story->share_id}")
        ->assertOk()
        ->assertSee('A flame for you.', false);
});

/**
 * Filters earn their place. Four controls sitting over three items is furniture, and a
 * page that just stopped being two tabs should not immediately become four buttons.
 */
it('holds the filter chips back until there is enough to sift', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $write = function (int $n, ?string $marker) use ($memorial) {
        foreach (range(1, $n) as $i) {
            $memorial->posts()->create([
                'type' => 'text',
                'content' => "Memory {$i}.",
                'tribute_type' => $marker,
                'is_published' => true,
            ]);
        }
    };

    $write(4, 'candle');
    $this->get("/{$memorial->slug}")->assertOk()->assertDontSee('data-story-marker', false);

    // Five items and more than one kind among them: now there is something to sift.
    $write(1, null);
    $this->get("/{$memorial->slug}")->assertOk()->assertSee('data-story-marker', false);
});

it('holds them back when everything is the same kind', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (range(1, 8) as $i) {
        $memorial->posts()->create(['type' => 'text', 'content' => "Memory {$i}.", 'is_published' => true]);
    }

    // Eight stories, all unmarked. "All 8 / Stories 8" is two buttons for one list.
    $this->get("/{$memorial->slug}")->assertOk()->assertDontSee('data-story-marker', false);
});

it('sends a bare tap share link back to the memorial rather than nowhere', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $tap = Tribute::create(['memorial_id' => $memorial->id, 'type' => 'flower', 'is_approved' => true]);

    $this->get("/{$memorial->slug}/tribute/{$tap->share_id}")
        ->assertRedirect("/{$memorial->slug}");
});