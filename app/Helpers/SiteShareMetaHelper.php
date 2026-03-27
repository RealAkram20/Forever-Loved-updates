<?php

namespace App\Helpers;

use App\Models\Page;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Default Open Graph / Twitter metadata for public marketing pages (app name, logo, tagline).
 */
class SiteShareMetaHelper
{
    public const DESCRIPTION_LIMIT = 320;

    /**
     * Use explicit $shareMeta from the controller when complete; otherwise build from page title.
     *
     * @param  array<string, mixed>|null  $shareMeta
     * @return array<string, mixed>
     */
    public static function resolve(?array $shareMeta, ?string $pageTitle = null): array
    {
        if (is_array($shareMeta)
            && ! empty($shareMeta['title'])
            && ! empty($shareMeta['description'])
            && ! empty($shareMeta['url'])) {
            return self::normalize($shareMeta);
        }

        $title = (is_string($pageTitle) && trim($pageTitle) !== '') ? trim($pageTitle) : 'Home';

        return self::forGenericPage($title);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function normalize(array $meta): array
    {
        $meta['site_name'] = $meta['site_name'] ?? self::appDisplayName();
        if (! empty($meta['image'])) {
            $img = MemorialShareMetaHelper::absoluteAssetUrl((string) $meta['image']);
            $meta['image'] = $img ?? $meta['image'];
        }

        return $meta;
    }

    public static function appDisplayName(): string
    {
        return (string) SystemSetting::get('branding.app_name', config('app.name', 'Forever Loved'));
    }

    public static function defaultSocialDescription(): string
    {
        $tagline = SystemSetting::get('branding.tagline', 'Celebrate lives that matter');

        return Str::limit(
            "{$tagline}. Create beautiful, lasting memorials to honor and celebrate the lives of those who matter most.",
            self::DESCRIPTION_LIMIT,
            '…'
        );
    }

    /**
     * Social preview image: brand logo when raster; otherwise favicon (many crawlers ignore SVG for og:image).
     */
    public static function siteShareImageUrl(): ?string
    {
        $logo = BrandingHelper::logoUrl();
        if ($logo !== '' && ! preg_match('/\.svg(\?|#|$)/i', $logo)) {
            return MemorialShareMetaHelper::absoluteAssetUrl($logo);
        }

        return MemorialShareMetaHelper::absoluteAssetUrl(BrandingHelper::faviconUrl());
    }

    /**
     * @return array{title: string, description: string, url: string, site_name: string, image?: string|null, image_alt?: string|null}
     */
    public static function forGenericPage(string $pageTitle, ?string $description = null, ?string $canonicalUrl = null): array
    {
        $appName = self::appDisplayName();
        $ogTitle = Str::limit(trim($pageTitle).' | '.$appName, 100, '…');
        $url = $canonicalUrl ?: URL::current();

        return [
            'title' => $ogTitle,
            'description' => $description
                ? Str::limit($description, self::DESCRIPTION_LIMIT, '…')
                : self::defaultSocialDescription(),
            'url' => $url,
            'site_name' => $appName,
            'image' => self::siteShareImageUrl(),
            'image_alt' => $appName,
        ];
    }

    /**
     * @return array{title: string, description: string, url: string, site_name: string, image?: string|null, image_alt?: string|null}
     */
    public static function forNamedRoute(string $pageTitle, string $routeName, array $parameters = [], ?string $description = null): array
    {
        $url = route($routeName, $parameters, true);

        return self::forGenericPage($pageTitle, $description, $url);
    }

    /**
     * @return array{title: string, description: string, url: string, site_name: string, image?: string|null, image_alt?: string|null}
     */
    public static function forHome(): array
    {
        $appName = self::appDisplayName();
        $tagline = SystemSetting::get('branding.tagline', 'Celebrate lives that matter');
        $title = Str::limit($appName.' — '.$tagline, 100, '…');

        return [
            'title' => $title,
            'description' => self::defaultSocialDescription(),
            'url' => route('home', [], true),
            'site_name' => $appName,
            'image' => self::siteShareImageUrl(),
            'image_alt' => $appName,
        ];
    }

    /**
     * CMS-backed visitor pages (about, privacy, terms).
     *
     * @return array{title: string, description: string, url: string, site_name: string, image?: string|null, image_alt?: string|null}
     */
    public static function forCmsPage(?Page $page, string $fallbackTitle, string $routeName, array $parameters = []): array
    {
        $heading = $page?->title ?? $fallbackTitle;
        $description = $page?->meta_description;
        if (empty($description) && $page && ! empty($page->content)) {
            $plain = preg_replace('/\s+/u', ' ', trim(strip_tags($page->content))) ?? '';
            $description = $plain !== '' ? Str::limit($plain, 280, '…') : null;
        }

        return self::forNamedRoute($heading, $routeName, $parameters, $description);
    }
}
