<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reactionable_type',
        'reactionable_id',
        'user_id',
        'guest_name',
        'guest_email',
        'type',
    ];

    public const TYPE_LIKE = 'like';
    public const TYPE_LOVE = 'love';
    public const TYPE_CANDLE = 'candle';
    public const TYPE_FLOWER = 'flower';

    public function reactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
