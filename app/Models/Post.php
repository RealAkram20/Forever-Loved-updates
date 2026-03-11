<?php

namespace App\Models;

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

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_LOCATION = 'location';
    public const TYPE_GALLERY = 'gallery';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';

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
