<?php

namespace App\Http\Controllers;

use App\Helpers\ThemeSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * /favicon.ico — served through code because half the things that show a site icon
 * never read the page's <link rel="icon"> at all. WhatsApp's link preview, older
 * crawlers and stray bookmarks simply fetch this path from the domain root, and it
 * used to be a static template icon that no admin setting could touch: the page said
 * one favicon, every WhatsApp card said another.
 *
 * Resolution mirrors BrandingHelper::faviconUrl(), and ThemeSetting resolves per
 * tenant — ResolveResellerByHost sits in the web group, so a reseller's own favicon
 * answers on their subdomain or custom domain without anything here knowing that.
 */
class FaviconController extends Controller
{
    private const MIME_BY_EXTENSION = [
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    public function show(): BinaryFileResponse
    {
        foreach (['branding.favicon_path', 'branding.logo_path'] as $key) {
            $path = ThemeSetting::get($key);
            if (! empty($path) && Storage::disk('public')->exists($path)) {
                return $this->file(Storage::disk('public')->path($path));
            }
        }

        return $this->file(public_path('images/favicon-default.ico'));
    }

    private function file(string $absolute): BinaryFileResponse
    {
        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        return response()->file($absolute, [
            'Content-Type' => self::MIME_BY_EXTENSION[$extension] ?? 'image/x-icon',
            // A day: long enough that browsers and crawlers are not hammering a PHP
            // route for an icon, short enough that a rebranding shows up tomorrow.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
