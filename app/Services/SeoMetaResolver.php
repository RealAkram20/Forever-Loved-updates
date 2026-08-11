<?php

namespace App\Services;

use App\Helpers\MemorialShareMetaHelper;
use App\Helpers\SiteShareMetaHelper;
use App\Models\Page;
use App\Models\SeoEntry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SeoMetaResolver
{
    /**
     * Marketing routes where DB SEO overrides apply (does not include memorial or auth flows).
     */
    public const ROUTES_WITH_OVERRIDES = [
        'home',
        'pricing',
        'about',
        'privacy-policy',
        'terms-of-use',
        'contact',
        'memorial.directory',
    ];

    /**
     * Route keys whose title/description/image are edited on the CMS page editor (not /pages/seo/...).
     */
    public static function pageSlugForMarketingRoute(string $routeKey): ?string
    {
        return match ($routeKey) {
            'about' => 'about',
            'privacy-policy' => 'privacy-policy',
            'terms-of-use' => 'terms-of-use',
            'memorial.directory' => Page::SLUG_FIND_MEMORIAL,
            default => null,
        };
    }

    public function applyToFullscreenLayout(View $view): void
    {
        $routeName = request()->route()?->getName();
        if (! $routeName || ! in_array($routeName, self::ROUTES_WITH_OVERRIDES, true)) {
            return;
        }

        $data = $view->getData();
        /** @var array<string, mixed>|null $shareMeta */
        $shareMeta = $data['shareMeta'] ?? null;
        if (! is_array($shareMeta)) {
            return;
        }

        $title = is_string($data['title'] ?? null) ? $data['title'] : 'Home';
        $page = isset($data['page']) && $data['page'] instanceof Page ? $data['page'] : null;

        $resolved = $this->resolve($routeName, $shareMeta, $title, $page);

        $view->with('shareMeta', $resolved['shareMeta']);
        $view->with('seoDocumentTitle', $resolved['documentTitle']);
    }

    /**
     * @param  array<string, mixed>  $fallbackShareMeta
     * @return array{shareMeta: array<string, mixed>, documentTitle: string}
     */
    public function resolve(string $routeName, array $fallbackShareMeta, string $pageHeading, ?Page $page = null): array
    {
        // Platform SEO overrides are the platform admin's marketing copy. On a tenant's
        // site they would stamp our language (and our uploaded og-image) onto pages the
        // reseller presents as their own — so a tenant host takes only its fallbacks.
        $entry = \App\Helpers\ThemeSetting::siteTenant()
            ? null
            : SeoEntry::query()->where('route_key', $routeName)->first();

        $metaTitle = $entry?->meta_title;
        $metaDescription = $entry?->meta_description;
        $ogImage = $entry?->og_image;

        if ($page) {
            if (filled($page->meta_title)) {
                $metaTitle = $page->meta_title;
            }
            if (filled($page->meta_description)) {
                $metaDescription = $page->meta_description;
            }
            if (filled($page->og_image)) {
                $ogImage = $page->og_image;
            }
        }

        $shareMeta = $fallbackShareMeta;

        if ($metaDescription !== null && $metaDescription !== '') {
            $shareMeta['description'] = Str::limit($metaDescription, SiteShareMetaHelper::DESCRIPTION_LIMIT, '…');
        }

        if ($metaTitle !== null && $metaTitle !== '') {
            $shareMeta['title'] = Str::limit($metaTitle, 100, '…');
        }

        if ($ogImage !== null && $ogImage !== '') {
            // StorageHelper roots at the request host; the disk's own url() is absolute
            // at APP_URL and would smuggle the platform hostname onto tenant pages.
            // Never null here — the guard above rules out the empty path.
            $relative = (string) \App\Helpers\StorageHelper::publicUrl($ogImage);
            $shareMeta['image'] = MemorialShareMetaHelper::absoluteAssetUrl($relative) ?? $relative;
            if (! empty($shareMeta['title'])) {
                $shareMeta['image_alt'] = is_string($shareMeta['title']) ? $shareMeta['title'] : '';
            }
        }

        $shareMeta = SiteShareMetaHelper::normalizeShareMeta($shareMeta);

        // The tab suffix belongs to whoever's site this is — "Privacy Policy | Kangaru
        // Ride" on their host, ours on ours.
        $appName = \App\Helpers\BrandingHelper::documentTitleName();
        $documentTitle = ($metaTitle !== null && $metaTitle !== '')
            ? Str::limit($metaTitle, 120, '')
            : (trim($pageHeading) !== '' ? $pageHeading.' | '.$appName : 'Home | '.$appName);

        return [
            'shareMeta' => $shareMeta,
            'documentTitle' => $documentTitle,
        ];
    }
}
