<?php

namespace App\Models;

use App\Support\SiteUrl;
use App\Support\StandardPages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'url',
        'route_name',
        'route_parameters',
        'open_in_new_tab',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'route_parameters' => 'array',
            'open_in_new_tab' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function resolvedUrl(): string
    {
        if (is_string($this->url) && $this->url !== '') {
            if (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://') || str_starts_with($this->url, '/')) {
                return $this->url;
            }

            return url($this->url);
        }

        $reseller = $this->menu?->reseller;

        if ($this->route_name === 'cms.page') {
            $slug = is_array($this->route_parameters) ? ($this->route_parameters['slug'] ?? null) : null;
            if (! is_string($slug) || $slug === '') {
                return '#';
            }

            // A reseller's page lives at *their* address. url() resolves against the current
            // request root, which is right on their own host but wrong for the /r/{slug} path
            // fallback that subdirectory and development installs use — there it would point
            // at the platform's /{slug} instead of theirs.
            if (! $reseller) {
                return url('/'.$slug);
            }

            return $slug === Page::SLUG_VISITOR_HOME
                ? $reseller->publicBaseUrl()
                : $reseller->publicUrlForSlug($slug);
        }

        if (is_string($this->route_name) && $this->route_name !== '' && Route::has($this->route_name)) {
            if ($reseller) {
                // A reseller's link has to point at *their* address, and neither route() nor a
                // relative path gets there on its own.
                //
                // route() is pinned to the platform by URL::forceRootUrl(config('app.url')),
                // which subdirectory installs need — so it would put a link to our site in
                // their navigation. The previous fix was to emit a relative path instead, on
                // the reasoning that it resolves against whatever host the visitor is already
                // on. That holds on a subdomain or a custom domain, where their root really is
                // `/`. It is wrong under the /r/{slug} fallback, where their root is
                // `/Forever/r/acme` — there a Home link emitted as `/` sent the visitor to the
                // platform's front page, which is exactly the hand-off all of this exists to
                // prevent.
                //
                // SiteUrl answers the question properly for both: it builds from
                // publicBaseUrl(), which already knows whether this environment can serve
                // their host or has to fall back to a path.
                if ($this->route_name === 'home') {
                    return SiteUrl::to('');
                }

                $standard = StandardPages::urlForRouteName($this->route_name);

                if ($standard !== null) {
                    return $standard;
                }

                // Anything outside the standard set keeps the relative form: it may point at
                // an app route with no per-tenant equivalent, and a path is still closer to
                // right than the platform's absolute URL.
                return route($this->route_name, $this->route_parameters ?? [], false);
            }

            return route($this->route_name, $this->route_parameters ?? []);
        }

        return '#';
    }

    /**
     * Value used in the admin route &lt;select&gt; (matches MenuController option keys).
     */
    public function routeSelectValue(): string
    {
        if (! is_string($this->route_name) || $this->route_name === '') {
            return '';
        }
        if ($this->route_name === 'cms.page') {
            $slug = is_array($this->route_parameters) ? ($this->route_parameters['slug'] ?? null) : null;
            if (is_string($slug) && $slug !== '') {
                return 'cms.page::'.$slug;
            }

            return '';
        }

        return $this->route_name;
    }

    /**
     * Whether this item points at the page currently being viewed.
     *
     * Compares **where the link goes**, not what route name it carries. Route names cannot
     * answer this: every item a reseller's menu builder creates is stored as `cms.page` with a
     * slug, while the pages themselves are served by `reseller.about`, `reseller.pricing`,
     * `reseller.public.index-path` and their equivalents on a real host — four different names
     * for the same page depending only on how the site was reached. Matching on the name meant
     * no reseller navigation ever showed an active item, on any host, which is where it
     * matters most: on a white-labeled site the nav is the only thing telling a visitor where
     * they are.
     *
     * resolvedUrl() already answers "their address, in this context" for all four modes, so
     * asking it and comparing paths gets the same answer everywhere without enumerating route
     * names that will drift again the next time a route is added.
     *
     * Both sides are absolutised before the path is taken, so the subdirectory install's
     * `/Forever` prefix cancels out rather than having to be special-cased.
     *
     * $currentRouteName is still accepted and still honoured as a fast path, because an item
     * that names a real route and matches it exactly is unambiguously active — and callers
     * already pass it.
     */
    public function isActive(?string $currentRouteName = null): bool
    {
        if ($currentRouteName
            && is_string($this->route_name)
            && $this->route_name !== ''
            && $this->route_name !== 'cms.page'
            && $this->route_name === $currentRouteName) {
            return true;
        }

        $target = $this->resolvedUrl();

        if ($target === '#' || $target === '') {
            return false;
        }

        return self::comparablePath($target) === self::comparablePath(request()->url());
    }

    /**
     * The path of a URL, in a form two URLs from different builders can be compared in.
     *
     * Relative results — route(..., absolute: false) for anything outside the standard set —
     * are absolutised first so they land in the same URL space as the request, prefix and all.
     * The trailing slash is dropped because a home link is `/acme` on one host and `/acme/` on
     * another and they are the same page.
     */
    private static function comparablePath(string $url): string
    {
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = url($url);
        }

        $path = parse_url($url, PHP_URL_PATH);

        return rtrim((string) ($path ?: '/'), '/') ?: '/';
    }
}
