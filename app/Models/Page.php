<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    /** Slugs that cannot be used (URL conflicts and app paths). */
    /** System-managed layout pages (not deletable; may omit /p/{slug} URL). */
    public const SLUG_VISITOR_HOME = 'visitor-home';

    public const SLUG_FIND_MEMORIAL = 'find-memorial';

    public static function reservedSlugs(): array
    {
        return [
            'api', 'p', 'page', 'pages', 'login', 'register', 'logout', 'password', 'email',
            'dashboard', 'settings', 'admin', 'install', 'updater', 'storage', 'sanctum',
            'memorials', 'users', 'notifications', 'profile', 'subscription', 'calendar',
            'create-memorial', 'find-memorial', 'pricing', 'about', 'contact',
            'privacy-policy', 'terms-of-use', 'payment', 'm', 'layout',
            self::SLUG_VISITOR_HOME,
        ];
    }

    public static function systemLayoutSlugs(): array
    {
        return [
            self::SLUG_VISITOR_HOME,
            'pricing',
            'contact',
            self::SLUG_FIND_MEMORIAL,
        ];
    }

    public function isSystemLayoutPage(): bool
    {
        return in_array($this->slug, self::systemLayoutSlugs(), true);
    }

    public function hasLayout(): bool
    {
        $layout = $this->layout;
        if (! is_array($layout) || empty($layout['widgets']) || ! is_array($layout['widgets'])) {
            return false;
        }

        return count($layout['widgets']) > 0;
    }

    /**
     * Public URL for this CMS page (fixed routes vs /p/{slug}).
     */
    public function publicUrl(): string
    {
        return match ($this->slug) {
            'about' => route('about'),
            'privacy-policy' => route('privacy-policy'),
            'terms-of-use' => route('terms-of-use'),
            self::SLUG_VISITOR_HOME => route('home'),
            'pricing' => route('pricing'),
            'contact' => route('contact'),
            self::SLUG_FIND_MEMORIAL => route('memorial.directory'),
            default => url('/' . $this->slug),
        };
    }

    protected $fillable = [
        'reseller_id',
        'slug',
        'title',
        'meta_title',
        'content',
        'layout',
        'meta_description',
        'og_image',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'layout' => 'array',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    /**
     * A platform page by slug (reseller_id IS NULL). Reseller pages are deliberately
     * invisible here so a tenant's "about" can never be served on the platform's own host.
     */
    public static function getBySlug(string $slug): ?self
    {
        return Cache::remember(self::platformCacheKey($slug), 3600, function () use ($slug) {
            return static::whereNull('reseller_id')->where('slug', $slug)->first();
        });
    }

    /** A specific reseller's page by slug, cached per tenant so slugs never collide across sites. */
    public static function getBySlugForReseller(string $slug, int $resellerId): ?self
    {
        return Cache::remember(self::resellerCacheKey($slug, $resellerId), 3600, function () use ($slug, $resellerId) {
            return static::where('reseller_id', $resellerId)->where('slug', $slug)->first();
        });
    }

    public static function clearSlugCache(string $slug, ?int $resellerId = null): void
    {
        Cache::forget($resellerId === null
            ? self::platformCacheKey($slug)
            : self::resellerCacheKey($slug, $resellerId));
    }

    private static function platformCacheKey(string $slug): string
    {
        return "page.platform.{$slug}";
    }

    private static function resellerCacheKey(string $slug, int $resellerId): string
    {
        return "page.reseller.{$resellerId}.{$slug}";
    }
}
