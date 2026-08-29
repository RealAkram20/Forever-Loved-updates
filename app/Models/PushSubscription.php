<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'memorial_subscription_id',
        'endpoint',
        'p256dh_key',
        'auth_token',
        'content_encoding',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The memorial subscription a guest's registration belongs to, and null for a signed-in
     * user's — theirs is a device that follows the account across every memorial, where a
     * guest's is one browser wanting news of one person.
     */
    public function memorialSubscription(): BelongsTo
    {
        return $this->belongsTo(MemorialSubscription::class);
    }
}
