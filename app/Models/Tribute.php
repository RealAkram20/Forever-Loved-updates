<?php

namespace App\Models;

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

    public const TYPE_FLOWER = 'flower';
    public const TYPE_CANDLE = 'candle';
    public const TYPE_NOTE = 'note';
    public const TYPE_IMAGE = 'image';

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
