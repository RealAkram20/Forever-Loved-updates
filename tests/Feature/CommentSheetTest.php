<?php

use App\Models\Comment;
use App\Models\Memorial;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function commentStory(?Memorial $memorial = null): Post
{
    $memorial ??= Memorial::factory()->create(['is_public' => true]);

    return $memorial->posts()->create(['type' => 'text', 'content' => 'A story.', 'is_published' => true]);
}

function writeComments(Post $post, int $n, ?int $parentId = null): void
{
    foreach (range(1, $n) as $i) {
        Comment::create([
            'post_id' => $post->id,
            'parent_id' => $parentId,
            'guest_name' => "Visitor {$i}",
            'guest_email' => "v{$i}@example.com",
            'content' => "Comment {$i}",
            'is_approved' => true,
        ]);
    }
}

/**
 * The sheet is built for a story with two hundred comments on it. The endpoint used to
 * return every one of them in a single array.
 */
it('returns one page at a time, newest first, and says whether there is more', function () {
    $post = commentStory();
    writeComments($post, 25);

    $first = $this->getJson("/m/{$post->memorial->slug}/posts/{$post->id}/comments")
        ->assertOk()
        ->assertJsonPath('total', 25)
        ->assertJsonPath('has_more', true)
        ->json('comments');

    expect($first)->toHaveCount(20)
        ->and($first[0]['content'])->toBe('Comment 25');

    $second = $this->getJson("/m/{$post->memorial->slug}/posts/{$post->id}/comments?before=".end($first)['id'])
        ->assertOk()
        ->assertJsonPath('has_more', false)
        ->json('comments');

    expect($second)->toHaveCount(5);

    // No comment appears on both pages — the whole reason this pages by id and not offset.
    $ids = array_merge(array_column($first, 'id'), array_column($second, 'id'));
    expect($ids)->toHaveCount(count(array_unique($ids)));
});

/**
 * What the open sheet polls for. Not a page — a gap.
 */
it('returns only what was written after the id the sheet already holds', function () {
    $post = commentStory();
    writeComments($post, 3);
    $latest = Comment::where('post_id', $post->id)->max('id');

    writeComments($post, 2);

    $fresh = $this->getJson("/m/{$post->memorial->slug}/posts/{$post->id}/comments?after={$latest}")
        ->assertOk()
        ->assertJsonPath('total', 5)
        ->json('comments');

    // Oldest first, so they stack onto the top of the list in the order they were written.
    expect($fresh)->toHaveCount(2)
        ->and($fresh[0]['content'])->toBe('Comment 1')
        ->and($fresh[1]['content'])->toBe('Comment 2');
});

it('counts replies in the total, because the header says "comments" and means all of them', function () {
    $post = commentStory();
    writeComments($post, 2);
    $parent = Comment::where('post_id', $post->id)->first();
    writeComments($post, 3, $parent->id);

    $this->getJson("/m/{$post->memorial->slug}/posts/{$post->id}/comments")
        ->assertOk()
        ->assertJsonPath('total', 5)
        // Only the two top-level ones are rows; the replies fold under one of them.
        ->assertJsonCount(2, 'comments');
});

it('previews three replies and reports how many there really are', function () {
    $post = commentStory();
    writeComments($post, 1);
    $parent = Comment::where('post_id', $post->id)->first();
    writeComments($post, 7, $parent->id);

    $row = $this->getJson("/m/{$post->memorial->slug}/posts/{$post->id}/comments")
        ->assertOk()
        ->json('comments.0');

    expect($row['reply_count'])->toBe(7)
        ->and($row['replies'])->toHaveCount(3);

    // And the rest arrive when somebody actually asks for them.
    $this->getJson("/m/{$post->memorial->slug}/comments/{$parent->id}/replies")
        ->assertOk()
        ->assertJsonCount(7, 'replies');
});

it('hearts a comment, and un-hearts it on a second press', function () {
    $post = commentStory();
    writeComments($post, 1);
    $comment = Comment::where('post_id', $post->id)->first();
    $visitor = User::factory()->create();

    $this->actingAs($visitor)
        ->postJson("/m/{$post->memorial->slug}/reaction", [
            'reactionable_type' => 'comment',
            'reactionable_id' => $comment->id,
            'type' => 'like',
        ])
        ->assertOk()
        ->assertJson(['action' => 'added', 'count' => 1]);

    $this->actingAs($visitor)
        ->postJson("/m/{$post->memorial->slug}/reaction", [
            'reactionable_type' => 'comment',
            'reactionable_id' => $comment->id,
            'type' => 'like',
        ])
        ->assertOk()
        ->assertJson(['action' => 'removed', 'count' => 0]);
});

it('tells you which comments you have already hearted', function () {
    $post = commentStory();
    writeComments($post, 2);
    $comments = Comment::where('post_id', $post->id)->orderBy('id')->get();
    $visitor = User::factory()->create();

    Reaction::create([
        'reactionable_type' => Comment::class,
        'reactionable_id' => $comments[0]->id,
        'user_id' => $visitor->id,
        'type' => 'like',
    ]);

    $rows = collect($this->actingAs($visitor)
        ->getJson("/m/{$post->memorial->slug}/posts/{$post->id}/comments")
        ->assertOk()
        ->json('comments'))
        ->keyBy('id');

    expect($rows[$comments[0]->id]['reacted'])->toBeTrue()
        ->and($rows[$comments[0]->id]['reaction_count'])->toBe(1)
        ->and($rows[$comments[1]->id]['reacted'])->toBeFalse();
});

/**
 * A comment has no memorial_id of its own. Without reaching it through its story, this
 * endpoint would like a comment on a private memorial by id and confirm it exists.
 */
it('will not heart a comment on a memorial other than the one in the url', function () {
    $mine = Memorial::factory()->create(['is_public' => true]);
    $theirs = Memorial::factory()->create(['is_public' => true]);
    $theirComment = Comment::create([
        'post_id' => commentStory($theirs)->id,
        'guest_name' => 'Someone',
        'guest_email' => 's@example.com',
        'content' => 'Private words.',
        'is_approved' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson("/m/{$mine->slug}/reaction", [
            'reactionable_type' => 'comment',
            'reactionable_id' => $theirComment->id,
            'type' => 'like',
        ])
        ->assertStatus(404);

    expect(Reaction::count())->toBe(0);
});

it('hands a posted comment back in the same shape the list uses', function () {
    $post = commentStory();

    $comment = $this->actingAs(User::factory()->create())
        ->postJson("/m/{$post->memorial->slug}/posts/{$post->id}/comments", ['content' => 'Thinking of you.'])
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->json('comment');

    // The sheet renders a comment it just posted through the same row renderer as one it
    // fetched, so every key that renderer reads has to be here.
    expect($comment)->toHaveKeys([
        'id', 'parent_id', 'content', 'author', 'author_photo',
        'created_at', 'created_at_iso', 'reaction_count', 'reacted', 'can_delete',
        'reply_count', 'replies',
    ]);
});