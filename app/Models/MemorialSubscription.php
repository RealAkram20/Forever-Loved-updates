<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorialSubscription extends Model
{
    protected $fillable = [
        'memorial_id',
        'user_id',
        'guest_name',
        'guest_email',
        'notify_life_chapters',
        'notify_tributes',
    ];

    protected function casts(): array
    {
        return [
            'notify_life_chapters' => 'boolean',
            'notify_tributes' => 'boolean',
        ];
    }

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSubscriberNameAttribute(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Subscriber';
    }

    public function getSubscriberEmailAttribute(): string
    {
        return $this->user?->email ?? $this->guest_email ?? '';
    }
}
