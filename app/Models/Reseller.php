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
        'billing_period_start',
        'billing_period_end',
    ];

    protected function casts(): array
    {
        return [
            'pesapal_enabled' => 'boolean',
            'pesapal_consumer_secret' => 'encrypted',
            'custom_domain_verified_at' => 'datetime',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
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

    /*
     |--------------------------------------------------------------------------
     | Tier entitlements
     |--------------------------------------------------------------------------
     | A tier is what the platform sold this reseller. Everything below reads from
     | it so the answer to "are they allowed to?" lives in one place.
     |
     | Two deliberately different defaults for a reseller with no tier assigned:
     | features are denied (they were never granted, and the widget already failed
     | closed this way), but quotas are treated as unmetered. Blocking creation
     | outright would make a freshly created reseller unusable until an admin
     | performs a second, easily forgotten step.
     */

    public function tierAllows(string $feature): bool
    {
        return (bool) ($this->tier?->{'feature_'.$feature} ?? false);
    }

    /** Included memorial profiles, or null for unlimited / unmetered. */
    public function memorialAllowance(): ?int
    {
        return $this->tier?->memorial_profile_allowance;
    }

    public function memorialsUsed(): int
    {
        return $this->memorials()->count();
    }

    /** Profiles left before overage, or null when there is no cap to run out of. */
    public function memorialsRemaining(): ?int
    {
        $allowance = $this->memorialAllowance();

        return $allowance === null ? null : max(0, $allowance - $this->memorialsUsed());
    }

    public function hasMemorialCapacity(): bool
    {
        return $this->memorialsRemaining() !== 0;
    }

    /**
     * Bytes across every memorial they host. Two aggregate queries rather than looping
     * Memorial::storageBytes(), which would be one query per memorial on a page that
     * already renders a list of them.
     *
     * Referenced size, not disk size — see Memorial::storageBytes().
     */
    public function storageUsedBytes(): int
    {
        $memorialIds = $this->memorials()->select('id');

        return (int) Media::whereIn('memorial_id', $memorialIds)->sum('size')
            + (int) $this->memorials()->sum('profile_photo_size');
    }

    /** The tier's storage cap in bytes, or null when uncapped / no tier assigned. */
    public function storageLimitBytes(): ?int
    {
        $gb = $this->tier?->storage_limit_gb;

        return $gb === null ? null : $gb * 1024 ** 3;
    }

    /** 0-100, or null when there is no cap to be a percentage of. */
    public function storagePercentUsed(): ?int
    {
        $limit = $this->storageLimitBytes();

        if ($limit === null || $limit === 0) {
            return null;
        }

        return (int) min(100, round($this->storageUsedBytes() / $limit * 100));
    }

    /*
     |--------------------------------------------------------------------------
     | Billing
     |--------------------------------------------------------------------------
     | What the reseller owes the platform, and whether they are current. Kept
     | gateway-agnostic: these answer "how much and when", not "how it was taken".
     */

    public const BILLING_NOT_STARTED = 'not_started';

    public const BILLING_ACTIVE = 'active';

    public const BILLING_DUE_SOON = 'due_soon';

    public const BILLING_OVERDUE = 'overdue';

    public function payments(): HasMany
    {
        return $this->hasMany(ResellerPayment::class)->latest('paid_at');
    }

    /** Profiles beyond the tier's included allowance. Zero when uncapped. */
    public function overageProfiles(): int
    {
        $allowance = $this->memorialAllowance();

        return $allowance === null ? 0 : max(0, $this->memorialsUsed() - $allowance);
    }

    public function overageAmount(): float
    {
        return round($this->overageProfiles() * (float) ($this->tier?->price_per_additional_profile ?? 0), 2);
    }

    /** Annual price plus whatever they have run over. */
    public function amountDue(): float
    {
        return round((float) ($this->tier?->annual_price ?? 0) + $this->overageAmount(), 2);
    }

    /**
     * Null until billing has started — deliberately not 0, so "never invoiced" and
     * "due today" can't be confused at a glance.
     */
    public function daysUntilRenewal(): ?int
    {
        return $this->billing_period_end
            ? (int) now()->startOfDay()->diffInDays($this->billing_period_end->startOfDay(), false)
            : null;
    }

    public function billingStatus(): string
    {
        $days = $this->daysUntilRenewal();

        return match (true) {
            $days === null => self::BILLING_NOT_STARTED,
            $days < 0 => self::BILLING_OVERDUE,
            $days <= 30 => self::BILLING_DUE_SOON,
            default => self::BILLING_ACTIVE,
        };
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
