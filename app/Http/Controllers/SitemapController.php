<?php

namespace App\Http\Controllers;

use App\Models\Memorial;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * /sitemap.xml — the crawler's map of everything public: the marketing pages and
 * every publicly visible memorial. Memorials are the product and the long tail of
 * search traffic; a person's name typed into Google should find their memorial, and
 * before this file existed discovery relied on whatever links happened to exist.
 *
 * Rebuilt at most hourly: memorial creation is not so frequent that a crawler needs
 * fresher, and the query walks every public memorial.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $entries = [];

            foreach (['home', 'pricing', 'about', 'memorial.directory', 'contact'] as $routeName) {
                $entries[] = ['loc' => route($routeName), 'changefreq' => 'weekly'];
            }

            Memorial::query()
                ->where('is_public', true)
                ->whereNotIn('status', ['deactivated', 'suspended'])
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderBy('id')
                ->select(['slug', 'updated_at'])
                ->chunk(500, function ($memorials) use (&$entries) {
                    foreach ($memorials as $memorial) {
                        $entries[] = [
                            'loc' => url('/'.$memorial->slug),
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
}
