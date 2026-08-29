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

    /**
     * The browsers registered for push under this subscription — a guest may have more than
     * one (a phone and a laptop), and a signed-in subscriber has none here, because theirs are
     * device registrations on the account rather than on the memorial.
     */
    public function pushSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PushSubscription::class);
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
