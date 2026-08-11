<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Written tributes become stories.
 *
 * A tribute row now means one thing only: somebody tapped Leave a Flower, Light a Candle
 * or Send a Prayer. The words people wrote used to live on that same row, which is what
 * forced the page to keep a second feed, a second card, a second composer and a sub-tab to
 * choose between them. Those words move here into `posts`, carrying their marker, and the
 * page is left with one feed.
 *
 * Three things travel with the words, because losing any of them would read to the author
 * as the site having eaten their tribute:
 *
 *  - the share id, so every link anyone has already sent still resolves (the tribute route
 *    redirects to the story route by that id — see PublicMemorialController::showTribute);
 *  - the reactions, re-pointed from the tribute to the story;
 *  - the comments, copied out of `tribute_comments` into `comments`, replies included.
 *
 * The tribute row itself is kept and its message left untouched. Keeping it is what makes
 * "12 candles" still true — writing a candle tribute was lighting a candle. Leaving the
 * message is belt and braces on live data: nothing reads it any more (`migrated_post_id`
 * marks it as spoken for), and it is the copy of record if this ever has to be undone.
 */
return new class extends Migration
{
    /** Rich-text markup an untouched Quill submits — words in name only. */
    private const EMPTY_MESSAGES = ['', '<p><br></p>', '<p></p>', '<p><br/></p>'];

    public function up(): void
    {
        Schema::table('tributes', function (Blueprint $table) {
            $table->unsignedBigInteger('migrated_post_id')->nullable()->after('message');
            $table->index('migrated_post_id');
        });

        $written = DB::table('tributes')
            ->whereNull('migrated_post_id')
            ->whereNotNull('message')
            ->whereNotIn('message', self::EMPTY_MESSAGES)
            ->orderBy('id')
            ->get();

        foreach ($written as $tribute) {
            $postId = DB::table('posts')->insertGetId([
                'memorial_id' => $tribute->memorial_id,
                'user_id' => $tribute->user_id,
                'share_id' => $this->claimShareId($tribute->share_id ?? null),
                'type' => 'text',
                'tribute_type' => $tribute->type,
                'title' => null,
                'content' => $tribute->message,
                // An unapproved tribute was never on the page; it must not appear on it now.
                'is_published' => (bool) $tribute->is_approved,
                'sort_order' => 0,
                'created_at' => $tribute->created_at,
                'updated_at' => $tribute->updated_at,
            ]);

            DB::table('reactions')
                ->where('reactionable_type', 'App\\Models\\Tribute')
                ->where('reactionable_id', $tribute->id)
                ->update([
                    'reactionable_type' => 'App\\Models\\Post',
                    'reactionable_id' => $postId,
                ]);

            $this->copyComments((int) $tribute->id, $postId);

            DB::table('tributes')->where('id', $tribute->id)->update(['migrated_post_id' => $postId]);
        }
    }

    public function down(): void
    {
        $migrated = DB::table('tributes')->whereNotNull('migrated_post_id')->get();

        foreach ($migrated as $tribute) {
            DB::table('reactions')
                ->where('reactionable_type', 'App\\Models\\Post')
                ->where('reactionable_id', $tribute->migrated_post_id)
                ->update([
                    'reactionable_type' => 'App\\Models\\Tribute',
                    'reactionable_id' => $tribute->id,
                ]);

            // The copied comments go with it: `comments.post_id` cascades on delete, and
            // the originals are still sitting in `tribute_comments` untouched.
            DB::table('posts')->where('id', $tribute->migrated_post_id)->delete();
        }

        Schema::table('tributes', function (Blueprint $table) {
            $table->dropIndex(['migrated_post_id']);
            $table->dropColumn('migrated_post_id');
        });
    }

    /**
     * The tribute's own share id, unless a story has somehow taken it already.
     *
     * The two tables generate ids from the same alphabet and never checked each other, so
     * a collision is unlikely but not impossible — and a duplicate here would fail the
     * unique index and take the whole migration down with it.
     */
    private function claimShareId(?string $preferred): string
    {
        if ($preferred && ! DB::table('posts')->where('share_id', $preferred)->exists()) {
            return $preferred;
        }

        do {
            $id = Str::lower(Str::random(7));
        } while (DB::table('posts')->where('share_id', $id)->exists());

        return $id;
    }

    /**
     * Copy a tribute's comment thread onto the story, in two passes so a reply can point at
     * the new id of the comment it answers rather than the old one.
     */
    private function copyComments(int $tributeId, int $postId): void
    {
        $comments = DB::table('tribute_comments')->where('tribute_id', $tributeId)->orderBy('id')->get();
        if ($comments->isEmpty()) {
            return;
        }

        $newIdFor = [];

        foreach ($comments->whereNull('parent_id') as $comment) {
            $newIdFor[$comment->id] = DB::table('comments')->insertGetId([
                'post_id' => $postId,
                'parent_id' => null,
                'user_id' => $comment->user_id,
                'guest_name' => $comment->guest_name,
                'guest_email' => $comment->guest_email,
                'content' => $comment->content,
                'is_approved' => $comment->is_approved,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
            ]);
        }

        foreach ($comments->whereNotNull('parent_id') as $reply) {
            // A reply whose parent is missing would otherwise be dropped silently; it is
            // still something a person wrote, so it is kept as a top-level comment.
            DB::table('comments')->insert([
                'post_id' => $postId,
                'parent_id' => $newIdFor[$reply->parent_id] ?? null,
                'user_id' => $reply->user_id,
                'guest_name' => $reply->guest_name,
                'guest_email' => $reply->guest_email,
                'content' => $reply->content,
                'is_approved' => $reply->is_approved,
                'created_at' => $reply->created_at,
                'updated_at' => $reply->updated_at,
            ]);
        }
    }
};