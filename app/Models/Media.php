<?php

namespace App\Models;

use App\Helpers\StorageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'memorial_id',
        'user_id',
        'gallery_category_id',
        'type',
        'path',
        'filename',
        'mime_type',
        'size',
        'sort_order',
        'caption',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public const TYPE_PHOTO = 'photo';
    public const TYPE_VIDEO = 'video';
    public const TYPE_MUSIC = 'music';

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    /**
     * The gallery category this is filed under, or null for unfiled. Null is the normal
     * resting state, not an error — filing is optional.
     */
    public function galleryCategory(): BelongsTo
    {
        return $this->belongsTo(GalleryCategory::class);
    }

    /**
     * The stories carrying this file. The inverse of Post::media(); mostly asked as
     * `posts()->exists()`, to tell a plain gallery upload from something a story depends on.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_media')->withPivot('sort_order');
    }

    /**
     * Get the public URL for this media file.
     */
    public function getUrlAttribute(): ?string
    {
        return StorageHelper::publicUrl($this->path);
    }
}
