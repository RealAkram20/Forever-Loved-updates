<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    use HasFactory;

    /**
     * A new reseller's site is usable the moment it exists.
     *
     * Standard pages and menus both defaulted to empty, so a brand-new tenant's site had no
     * About, no Pricing, no Contact and a header reading "Home" and nothing else — every one
     * of them a switch somebody had to find first, and some of them behind a tier flag they
     * may not have. Provisioning here rather than in Admin\ResellerController for the reason
     * Memorial::booted() gives about reseller_id: there is more than one way a reseller gets
     * created (the admin form, a seeder, a factory), and a hook cannot be forgotten by the
     * next one.
     *
     * ResellerSiteProvisioner is additive and idempotent, so this only ever fills in what is
     * missing — a reseller who later switches a page off keeps it off.
     */
    protected static function booted(): void
    {
        static::created(function (self $reseller) {
            \App\Support\ResellerSiteProvisioner::provision($reseller);
        });
    }

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const DOMAIN_UNVERIFIED = 'unverified';

    public const DOMAIN_VERIFIED = 'verified';

    public const DOMAIN_FAILED = 'failed';

    protected $fillable = [
        'name',
        // Where their Contact page sends enquiries. Seeded from the owner's account so it
        // works immediately; separable because enquiries usually belong in a shared inbox.
        'contact_email',
        'slug',
        'owner_user_id',
        'reseller_tier_id',
        'status',
        'logo_path',
        'logo_dark_path',
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

    /**
     * Whether this reseller has a usable Pesapal merchant account of their own.
     *
     * PesapalService::forReseller() silently falls back to the *platform's* credentials
     * when this is false, so anything that would act on "their" gateway — registering an
     * IPN, taking their clients' money — must check this first. Otherwise the reseller
     * sees "enabled" while the money lands with the platform.
     */
    public function hasOwnPesapalCredentials(): bool
    {
        return $this->pesapal_enabled
            && filled($this->pesapal_consumer_key)
            && filled($this->pesapal_consumer_secret);
    }

    public function hasVerifiedCustomDomain(): bool
    {
        return $this->custom_domain !== null && $this->custom_domain_status === self::DOMAIN_VERIFIED;
    }

    /*
     |--------------------------------------------------------------------------
     | Public addresses
     |--------------------------------------------------------------------------
     | Everything that shows or links to a reseller address goes through here.
     | Nine views used to concatenate slug + config('reseller.domain') inline, which
     | printed a confident "acme.<base domain>" on a localhost subdirectory install
     | — an address nothing can serve, offered to the reseller as final.
     |
     | These answer two separate questions: where the pages are *meant* to live once
     | deployed (publicHost), and where they can actually be reached *right now*
     | (publicBaseUrl). Those differ in every environment that isn't production.
     */

    /**
     * Host-header routing needs the app to own the whole document root. Route::domain()
     * carries no path prefix, so on a subdirectory install — APP_URL ending in /Forever —
     * there is no URL that reaches a host-routed page at all, for subdomains or for a
     * reseller's own custom domain.
     */
    public static function hostRoutingAvailable(): bool
    {
        return trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/') === '';
    }

    /**
     * Subdomains additionally need the app to actually answer on the reseller base domain.
     * Pointing RESELLER_APP_DOMAIN at a domain the app is not served from makes every
     * minted {slug}.{base} address dead on arrival.
     *
     * The base domain now derives from APP_URL rather than a hardcoded one, so this is
     * true by default on a real deployment instead of needing a matching env var — but a
     * single-label host is still rejected below.
     */
    public static function subdomainRoutingAvailable(): bool
    {
        if (! self::hostRoutingAvailable()) {
            return false;
        }

        $host = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));
        $base = strtolower((string) config('reseller.domain'));

        // A base with no dot is a bare hostname — 'localhost', or a machine name on a LAN.
        // Some browsers resolve acme.localhost and most systems do not, so we refuse to
        // hand anyone that address and fall back to the /r/{slug} path instead.
        if (! str_contains($base, '.')) {
            return false;
        }

        return $host !== '' && $base !== '' && ($host === $base || str_ends_with($host, '.'.$base));
    }

    /** The host these pages are meant to be served from once deployed correctly. */
    public function publicHost(): string
    {
        return $this->hasVerifiedCustomDomain()
            ? $this->custom_domain
            : $this->slug.'.'.config('reseller.domain');
    }

    /**
     * True when this environment cannot serve publicHost(), so publicBaseUrl() is handing
     * back a development stand-in. The UI must say so rather than presenting a dead
     * address as the one to give a family.
     */
    public function usingFallbackAddress(): bool
    {
        return $this->hasVerifiedCustomDomain()
            ? ! self::hostRoutingAvailable()
            : ! self::subdomainRoutingAvailable();
    }

    /**
     * The base URL that actually works right now — the real host when host routing is
     * available, else the path-based /r/{slug} route, so reseller pages are reachable in
     * development instead of only existing in production.
     */
    public function publicBaseUrl(): string
    {
        // Built from config, not url(): the availability checks above read config('app.url'),
        // and url() resolves against the *current request's* root. Letting the two disagree
        // would make this return a different address inside a queued job or console command
        // (notifications, invites) than it does in a web request.
        if ($this->usingFallbackAddress()) {
            return rtrim((string) config('app.url'), '/').'/r/'.$this->slug;
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$this->publicHost();
    }

    /** Absolute URL for one of this reseller's memorial slugs. */
    public function publicUrlForSlug(string $slug): string
    {
        return $this->publicBaseUrl().'/'.$slug;
    }

    /** publicBaseUrl() without the scheme, for display in a <code> block. */
    public function publicDisplayAddress(): string
    {
        return (string) preg_replace('#^https?://#', '', $this->publicBaseUrl());
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
            + (int) $this->memorials()->sum('profile_photo_size')
            + (int) $this->memorials()->sum('cover_photo_size');
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
    public static function applyFilter($query, mixed $value)
    {
        // Query input is attacker-controlled shape, not just value: `?reseller[]=1` arrives
        // as an array and a string type-hint would TypeError into a 500 on every list page
        // that offers this filter.
        if (! is_scalar($value)) {
            return $query;
        }

        $value = (string) $value;

        if ($value === '') {
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
