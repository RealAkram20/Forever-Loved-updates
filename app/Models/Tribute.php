<?php

namespace App\Models;

use App\Helpers\HtmlHelper;
use Illuminate\Database\Eloquent\Builder;
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
     * The ones somebody actually wrote something on.
     *
     * A tap on one of the one-tap cards stores a tribute with no message. That is a
     * reaction, not a post — it belongs in the tally under the card and nowhere else,
     * the same way a like does. Only tributes carrying words belong in the feed.
     *
     * The empty rich-text cases are listed out because the editor submits markup, not an
     * empty string, when someone opens the composer and types nothing.
     */
    public function scopeWithMessage(Builder $query): Builder
    {
        return $query->whereNotNull('message')
            ->whereNotIn('message', ['', '<p><br></p>', '<p></p>', '<p><br/></p>']);
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
