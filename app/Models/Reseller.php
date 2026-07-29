<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const DOMAIN_UNVERIFIED = 'unverified';

    public const DOMAIN_VERIFIED = 'verified';

    public const DOMAIN_FAILED = 'failed';

    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
        'reseller_tier_id',
        'status',
        'logo_path',
        'favicon_path',
        'primary_color',
        'pesapal_enabled',
        'pesapal_consumer_key',
        'pesapal_consumer_secret',
        'pesapal_environment',
        'pesapal_ipn_id',
        'custom_domain',
        'custom_domain_token',
        'custom_domain_status',
        'custom_domain_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'pesapal_enabled' => 'boolean',
            'pesapal_consumer_secret' => 'encrypted',
            'custom_domain_verified_at' => 'datetime',
        ];
    }

    public function hasVerifiedCustomDomain(): bool
    {
        return $this->custom_domain !== null && $this->custom_domain_status === self::DOMAIN_VERIFIED;
    }

    /**
     * Subdomain labels that must never be claimable by a reseller, since they either
     * collide with reserved apex-domain routes or common infrastructure hostnames.
     * The hardcoded list is the non-negotiable floor; admins can fence off additional
     * labels (brand terms, upcoming product names) from the reseller Settings page.
     */
    public static function reservedSlugs(): array
    {
        $extra = preg_split('/[\s,]+/', (string) SystemSetting::get('reseller.reserved_slugs', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_merge(self::baseReservedSlugs(), array_map('strtolower', $extra))));
    }

    /** The non-negotiable floor, surfaced separately so the settings page can show it. */
    public static function baseReservedSlugs(): array
    {
        return ['www', 'app', 'api', 'admin', 'm', 'mail', 'ftp', 'cpanel', 'embed', 'widget'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(ResellerTier::class, 'reseller_tier_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function memorials(): HasMany
    {
        return $this->hasMany(Memorial::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class);
    }

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Constrain any reseller_id-bearing query from a request filter value. Shared by the
     * platform-wide Users / Memorials / Plans / Payment Orders lists, which all span both
     * direct and reseller-owned records and otherwise give no way to tell them apart.
     *
     * '' or null → everything, 'direct' → platform-owned only, a numeric id → that reseller.
     */
    public static function applyFilter($query, ?string $value)
    {
        if ($value === null || $value === '') {
            return $query;
        }

        if ($value === 'direct') {
            return $query->whereNull('reseller_id');
        }

        return ctype_digit($value) ? $query->where('reseller_id', (int) $value) : $query;
    }

    /** Resellers for a filter dropdown — id and name only, cheapest possible. */
    public static function filterOptions()
    {
        return self::orderBy('name')->get(['id', 'name']);
    }
}
