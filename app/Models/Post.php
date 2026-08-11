<?php

namespace App\Models;

use App\Helpers\HtmlHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'share_id',
        'memorial_id',
        'story_chapter_id',
        'user_id',
        'type',
        'tribute_type',
        'title',
        'content',
        'location',
        'metadata',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Sanitised on read, for the same reason as Tribute::getMessageAttribute(): the Life
     * feed's JS assigns this straight to innerHTML, and rows written before content was
     * sanitised on save still hold whatever was submitted.
     */
    public function getContentAttribute(?string $value): ?string
    {
        return $value === null ? null : HtmlHelper::sanitize($value);
    }

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_LOCATION = 'location';
    public const TYPE_GALLERY = 'gallery';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';

    /**
     * What the author said this story was, in their own words.
     *
     * A marker is optional and that is the point: most people arrive with something to
     * say, not with a category to pick. `null` is a plain story and needs no explaining.
     * The keys are Tribute::TYPES, so the marker on a story and the card somebody taps to
     * light a candle are the same three things rather than two parallel vocabularies.
     */
    public const MARKER_VERBS = [
        Tribute::TYPE_FLOWER => 'left a flower',
        Tribute::TYPE_CANDLE => 'lit a candle',
        Tribute::TYPE_PRAYER => 'said a prayer',
    ];

    /**
     * "lit a candle", or null for a story that was left unmarked. Reads as a sentence
     * beside the author's name, which is the only place it is shown.
     */
    public function markerVerb(): ?string
    {
        return self::MARKER_VERBS[$this->tribute_type] ?? null;
    }

    /**
     * Published stories, newest first — the memorial's one feed.
     */
    public function scopeFeed(Builder $query): Builder
    {
        return $query->where('is_published', true)->latest();
    }

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    public function storyChapter(): BelongsTo
    {
        return $this->belongsTo(StoryChapter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'post_media')->withPivot('sort_order')->orderBy('post_media.sort_order');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactionable');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->where('is_approved', true)->with(['user', 'replies'])->latest();
    }

    public function allComments()
    {
        return $this->hasMany(Comment::class)->where('is_approved', true);
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            if (empty($post->share_id)) {
                $post->share_id = static::generateUniqueShareId();
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
