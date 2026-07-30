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

    /**
     * The only plans a given viewer may see or buy: their reseller's if they belong to
     * one, the platform's own otherwise.
     *
     * Every public and customer-facing plan list must go through this. Without it these
     * queries returned *every* plan in the table, publishing each reseller's private
     * pricing on the platform's own pricing page and letting anyone subscribe to another
     * tenant's plan — including a free one, for that tenant's entitlements at no cost.
     */
    /**
     * Plans a given *person* may buy — keyed off who they already belong to.
     *
     * Right for the dashboard and the signup flow, where the viewer is signed in and their
     * reseller_id is the answer. Wrong for a public page, where the visitor is usually
     * anonymous: see scopeSellableOnHost().
     */
    public function scopeSellableTo($query, ?User $viewer = null)
    {
        return $viewer?->reseller_id
            ? $query->where('reseller_id', $viewer->reseller_id)
            : $query->whereNull('reseller_id');
    }

    /**
     * Plans a given *site* is selling — keyed off whose host is being served.
     *
     * A public pricing page has to ask this question rather than sellableTo(): a visitor with
     * no account, or with an account belonging to a different reseller, would otherwise be
     * shown the platform's plans (or another tenant's) on a reseller's own domain. The viewer
     * is only consulted as a tiebreak when there is no tenant on the request, which keeps the
     * platform's own pricing page behaving exactly as before.
     */
    public function scopeSellableOnHost($query, ?int $resellerId = null, ?User $viewer = null)
    {
        $resellerId ??= $viewer?->reseller_id;

        return $resellerId
            ? $query->where('reseller_id', $resellerId)
            : $query->whereNull('reseller_id');
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
