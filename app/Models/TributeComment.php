<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TributeComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tribute_id',
        'parent_id',
        'user_id',
        'guest_name',
        'guest_email',
        'content',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
        ];
    }

    public function tribute(): BelongsTo
    {
        return $this->belongsTo(Tribute::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TributeComment::class, 'parent_id');
    }

    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TributeComment::class, 'parent_id')->where('is_approved', true)->with('user')->latest();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAuthorNameAttribute(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Anonymous';
    }
}
