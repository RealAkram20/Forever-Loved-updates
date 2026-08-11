<?php

namespace App\Models;

use App\Helpers\HtmlHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Tribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'share_id',
        'memorial_id',
        'user_id',
        'type',
        'message',
        'migrated_post_id',
        'guest_name',
        'guest_email',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
        ];
    }

    /**
     * Sanitised on the way out, not only on the way in.
     *
     * Writes have been sanitised for a while, but two things that did not cover: rows stored
     * before that (a note left by any visitor, kept verbatim), and the JSON API, which handed
     * `message` to the memorial page's JS to be assigned to innerHTML. Cleaning at the read
     * boundary closes both at once — no backfill migration, and a serializer added later is
     * safe without its author having to remember.
     */
    public function getMessageAttribute(?string $value): ?string
    {
        return $value === null ? null : HtmlHelper::sanitize($value);
    }

    public const TYPE_FLOWER = 'flower';

    public const TYPE_CANDLE = 'candle';

    public const TYPE_PRAYER = 'prayer';

    /**
     * The types a tribute may be created with, in the order they are offered.
     *
     * Validation reads this rather than repeating an `in:` list, so adding a fourth type
     * is one edit here instead of a hunt through the controllers.
     */
    public const TYPES = [
        self::TYPE_FLOWER,
        self::TYPE_CANDLE,
        self::TYPE_PRAYER,
    ];

    /**
     * The story a tribute's words were moved to, once they had any.
     *
     * A tribute is now a tap and nothing else — the gesture, counted under the card that
     * offers it, the same way a like is counted. Anything anyone writes is a story, so
     * the words that used to live on this row were moved into `posts`; this points at
     * where they went. Set only by the migration, never by the app.
     *
     * @see database/migrations/2026_08_08_100001_move_written_tributes_into_stories.php
     */
    public function migratedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'migrated_post_id');
    }

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactionable');
    }

    /**
     * Comments left on a tribute back when a tribute could carry words.
     *
     * Nothing writes here any more and nothing renders it: the words moved to stories and
     * their comment threads went with them, into `comments`. The relation is kept because
     * `tribute_comments` still holds the originals — the copy of record if the move ever
     * has to be undone — and a row nobody can reach is easier to explain than a table with
     * no way back to it.
     *
     * @see database/migrations/2026_08_08_100001_move_written_tributes_into_stories.php
     */
    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TributeComment::class)->whereNull('parent_id')->where('is_approved', true)->with(['user', 'replies'])->latest();
    }

    protected static function booted(): void
    {
        static::creating(function (Tribute $tribute) {
            if (empty($tribute->share_id)) {
                $tribute->share_id = static::generateUniqueShareId();
            }
        });
    }

    public static function generateUniqueShareId(): string
    {
        do {
            $id = Str::lower(Str::random(7));
        } while (static::where('share_id', $id)->exists());
        return $id;
    }
}
