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
     * The path this page is served at, relative to whichever site owns it.
     */
    public function publicPath(): string
    {
        return match ($this->slug) {
            self::SLUG_VISITOR_HOME => '/',
            'about', 'privacy-policy', 'terms-of-use', 'pricing', 'contact', self::SLUG_FIND_MEMORIAL => '/'.$this->slug,
            default => '/'.$this->slug,
        };
    }

    /**
     * Public URL for this CMS page.
     *
     * A reseller's page is addressed on *their* site. route()/url() resolve against the
     * current request root, which is right while serving their host but wrong everywhere
     * else — an admin previewing it, a queued notification, or the /r/{slug} path fallback
     * that subdirectory and development installs use. publicBaseUrl() is built from config
     * and answers the same in all of those.
     */
    public function publicUrl(): string
    {
        if ($this->reseller_id) {
            $base = $this->reseller?->publicBaseUrl();

            if ($base) {
                $path = $this->publicPath();

                return $path === '/' ? $base : $base.$path;
            }
        }

        return match ($this->slug) {
            'about' => route('about'),
            'privacy-policy' => route('privacy-policy'),
            'terms-of-use' => route('terms-of-use'),
            self::SLUG_VISITOR_HOME => route('home'),
            'pricing' => route('pricing'),
            'contact' => route('contact'),
            self::SLUG_FIND_MEMORIAL => route('memorial.directory'),
            default => url('/'.$this->slug),
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
     * A page by slug, for one tenant — or, for null, the platform's own.
     *
     * The tenant argument used to be a separate method, which meant every visitor controller
     * called the platform-only variant and could not see a reseller's equivalent even while
     * serving that reseller's host. Passing the tenant explicitly (rather than reading it
     * from the container here) keeps this usable from queued jobs and console commands, where
     * there is no request to resolve a tenant from.
     *
     * Reseller pages stay invisible to a null lookup, so a tenant's "about" can never be
     * served on the platform's own host.
     */
    public static function getBySlug(string $slug, ?int $resellerId = null): ?self
    {
        $key = $resellerId === null
            ? self::platformCacheKey($slug)
            : self::resellerCacheKey($slug, $resellerId);

        return Cache::remember($key, 3600, function () use ($slug, $resellerId) {
            return static::query()
                ->when($resellerId === null,
                    fn ($q) => $q->whereNull('reseller_id'),
                    fn ($q) => $q->where('reseller_id', $resellerId),
                )
                ->where('slug', $slug)
                ->first();
        });
    }

    /** A specific reseller's page by slug, cached per tenant so slugs never collide across sites. */
    public static function getBySlugForReseller(string $slug, int $resellerId): ?self
    {
        return self::getBySlug($slug, $resellerId);
    }

    /**
     * The page a visitor should actually be served for this slug, or null when there is none
     * to show. Unpublished counts as none — that is what the enable/disable switch means.
     */
    public static function publishedFor(string $slug, ?int $resellerId = null): ?self
    {
        $page = self::getBySlug($slug, $resellerId);

        return $page?->is_published ? $page : null;
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
