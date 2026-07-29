<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'reseller_id',
        'name',
        'slug',
        'description',
        'price',
        'interval',
        'memorial_limit',
        'storage_limit_mb',
        'max_gallery_images',
        'max_gallery_videos',
        'max_tributes',
        'max_chapters',
        'max_ai_bio_per_day',
        'feature_background_music',
        'feature_advanced_privacy',
        'feature_guest_notifications',
        'feature_never_expires',
        'feature_no_ads',
        'feature_share_memories',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'memorial_limit' => 'integer',
            'storage_limit_mb' => 'integer',
            'max_gallery_images' => 'integer',
            'max_gallery_videos' => 'integer',
            'max_tributes' => 'integer',
            'max_chapters' => 'integer',
            'max_ai_bio_per_day' => 'integer',
            'feature_background_music' => 'boolean',
            'feature_advanced_privacy' => 'boolean',
            'feature_guest_notifications' => 'boolean',
            'feature_never_expires' => 'boolean',
            'feature_no_ads' => 'boolean',
            'feature_share_memories' => 'boolean',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The plan the pricing views badge as "Most Popular" and preselect, scoped to the
     * platform's own global plans (reseller_id null) unless a reseller is given.
     * At most one plan per scope carries the flag; admin sets it in Settings → Plans.
     */
    public static function popular(?int $resellerId = null): ?self
    {
        return static::query()->where('is_active', true)->where('is_popular', true)
            ->where('reseller_id', $resellerId)->first();
    }

    /**
     * Clear the flag on every other plan in the same scope (global vs. this reseller's own) —
     * "most popular" is a single choice per scope, not across the whole platform.
     */
    public function makeSolePopular(): void
    {
        static::query()->where('id', '!=', $this->id)->where('is_popular', true)
            ->where('reseller_id', $this->reseller_id)->update(['is_popular' => false]);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('reseller_id');
    }

    public function scopeForReseller($query, int $resellerId)
    {
        return $query->where('reseller_id', $resellerId);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'subscription_plan_id');
    }

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }

    public function allowsAiBio(): bool
    {
        return $this->max_ai_bio_per_day > 0;
    }
}
