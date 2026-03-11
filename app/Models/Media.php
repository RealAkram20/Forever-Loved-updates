<?php

namespace App\Models;

use App\Helpers\StorageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'memorial_id',
        'user_id',
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
     * Get the public URL for this media file.
     */
    public function getUrlAttribute(): ?string
    {
        return StorageHelper::publicUrl($this->path);
    }
}
