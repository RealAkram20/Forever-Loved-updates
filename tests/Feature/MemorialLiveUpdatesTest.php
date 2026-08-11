<?php

use App\Models\Comment;
use App\Models\Memorial;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The endpoint the open page polls: every story's current tallies plus anything
 * published since the id the client last saw — what makes likes, comments and new
 * stories land without anyone reloading.
 */
function liveMemorial(): array
{
    $author = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true, 'user_id' => $author->id]);
    $post = Post::create([
        'memorial_id' => $memorial->id,
        'user_id' => $author->id,
        'type' => 'text',
        'content' => 'A story already on the page.',
        'is_published' => true,
        'sort_order' => 0,
    ]);

    return [$memorial, $post, $author];
}

it('reports current reaction and comment tallies', function () {
    [$memorial, $post, $author] = liveMemorial();

    Reaction::create([
        'reactionable_type' => Post::class,
        'reactionable_id' => $post->id,
        'user_id' => $author->id,
        'type' => 'like',
    ]);
    Comment::create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'content' => 'We miss you.',
        'is_approved' => true,
    ]);
    Comment::create([
        'post_id' => $post->id,
        'user_id' => $author->id,
        'content' => 'Held for review.',
        'is_approved' => false,
    ]);

    $this->getJson("/m/{$memorial->slug}/live")
        ->assertOk()
        ->assertJsonPath("reactions.{$post->id}", 1)
        // The unapproved comment must not be counted before a moderator has seen it.
        ->assertJsonPath("comments.{$post->id}", 1)
        ->assertJsonPath('latest_post_id', $post->id);
});

it('hands over stories published after the id the client last saw', function () {
    [$memorial, $post, $author] = liveMemorial();

    $newer = Post::create([
        'memorial_id' => $memorial->id,
        'user_id' => $author->id,
        'type' => 'text',
        'tribute_type' => 'candle',
        'content' => 'Written while the page was open.',
        'is_published' => true,
        'sort_order' => 0,
    ]);
    Post::create([
        'memorial_id' => $memorial->id,
        'user_id' => $author->id,
        'type' => 'text',
        'content' => 'A draft nobody should receive.',
        'is_published' => false,
        'sort_order' => 0,
    ]);

    $response = $this->getJson("/m/{$memorial->slug}/live?after_id={$post->id}")->assertOk();

    expect($response->json('new_posts'))->toHaveCount(1)
        ->and($response->json('new_posts.0.id'))->toBe($newer->id)
        ->and($response->json('new_posts.0.tribute_type'))->toBe('candle')
        ->and($response->json('new_posts.0.author'))->toBe($author->name);
});

it('sends no back-catalogue when the client asks without a cursor', function () {
    [$memorial] = liveMemorial();

    $this->getJson("/m/{$memorial->slug}/live")
        ->assertOk()
        ->assertJsonCount(0, 'new_posts');
});

it('refuses a private memorial to strangers', function () {
    [$memorial] = liveMemorial();
    $memorial->update(['is_public' => false]);

    $this->getJson("/m/{$memorial->slug}/live")->assertNotFound();
});
