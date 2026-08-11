<?php

namespace App\Http\Controllers;

use App\Helpers\ThemeSetting;
use App\Models\Memorial;
use App\Support\SiteUrl;
use App\Support\StandardPages;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * /sitemap.xml — the crawler's map of whichever site is being served.
 *
 * On the platform's host: the marketing pages and every publicly visible
 * platform-owned memorial. On a reseller's host: their front page, whichever
 * standard pages they switched on, and their own public memorials — nothing else's.
 * The cache is keyed per tenant, because a single key meant whichever host warmed
 * it first served its sitemap to every other host for an hour.
 *
 * Memorial entries use publicUrl(), so the loc and the page's own canonical always
 * agree — a reseller memorial is never advertised at a platform address it would
 * immediately canonicalize away from.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $tenant = ThemeSetting::siteTenant();
        $cacheKey = 'sitemap.xml.'.($tenant?->id ?? 'platform');

        $xml = Cache::remember($cacheKey, now()->addHour(), function () use ($tenant) {
            $entries = [];

            if ($tenant) {
                $entries[] = ['loc' => $tenant->publicBaseUrl(), 'changefreq' => 'weekly'];

                foreach (['about', 'pricing', 'contact', 'find-memorial'] as $slug) {
                    if (StandardPages::isEnabledFor($slug, $tenant->id)) {
                        $entries[] = ['loc' => SiteUrl::to($slug), 'changefreq' => 'weekly'];
                    }
                }
            } else {
                foreach (['home', 'pricing', 'about', 'memorial.directory', 'contact'] as $routeName) {
                    $entries[] = ['loc' => route($routeName), 'changefreq' => 'weekly'];
                }
            }

            Memorial::query()
                ->where('is_public', true)
                ->whereNotIn('status', ['deactivated', 'suspended'])
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->when($tenant,
                    fn ($q) => $q->where('reseller_id', $tenant->id),
                    fn ($q) => $q->whereNull('reseller_id'))
                // Narrow select: publicUrl() needs the slug and the reseller relation,
                // not the biography blobs. The reseller is the $tenant we already hold
                // (or null on the platform branch), so no eager load is needed either.
                ->select(['id', 'slug', 'updated_at', 'reseller_id'])
                ->orderBy('id')
                ->chunk(500, function ($memorials) use (&$entries, $tenant) {
                    foreach ($memorials as $memorial) {
                        if ($tenant) {
                            $memorial->setRelation('reseller', $tenant);
                        }
                        $entries[] = [
                            'loc' => $memorial->publicUrl(),
                            'lastmod' => $memorial->updated_at?->toAtomString(),
                            'changefreq' => 'weekly',
                        ];
                    }
                });

            $urls = collect($entries)->map(function (array $e) {
                $parts = '<loc>'.e($e['loc']).'</loc>';
                if (! empty($e['lastmod'])) {
                    $parts .= '<lastmod>'.e($e['lastmod']).'</lastmod>';
                }
                $parts .= '<changefreq>'.$e['changefreq'].'</changefreq>';

                return '<url>'.$parts.'</url>';
            })->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?>'
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .$urls
                .'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * robots.txt used to be a static file naming the platform's sitemap URL — served
     * byte-identically on every host, it pointed crawlers on a reseller's domain at
     * our sitemap. Served from code, each host names its own.
     */
    public function robots(): Response
    {
        return response(
            "User-agent: *\nDisallow:\n\nSitemap: ".SiteUrl::to('sitemap.xml')."\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
