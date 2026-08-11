<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * The image a link unfurls into on WhatsApp, Facebook, and the rest.
 *
 * Crawlers are stricter than browsers: WhatsApp silently drops any og:image over
 * ~600KB, and families upload photos far larger than that — which is how a memorial
 * came to share as a bare grey card. So the page never offers the original: it offers
 * a 1200×630 JPEG derivative made here, a size and weight every crawler accepts.
 *
 * Derivatives are made once and kept next to nothing else in a share/ folder under
 * the source's directory. The filename is a hash of the source path, so replacing a
 * profile photo simply produces a new derivative and the stale one stops being
 * referenced. Failure to generate (corrupt upload, exotic format) falls back to the
 * original image URL — a possibly-dropped preview image, never a broken page.
 */
class ShareImageService
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const QUALITY = 82;

    /**
     * @return array{url: string, width: int, height: int, type: string}|null
     *   Absolute URL and declared dimensions for the derivative, or null when there is
     *   no source image or the derivative cannot be made and the caller should fall
     *   back to whatever it has.
     */
    public function derive(?string $sourcePath): ?array
    {
        if ($sourcePath === null || trim($sourcePath) === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $derivativePath = dirname($sourcePath).'/share/og-'.md5($sourcePath).'.jpg';

        if (! $disk->exists($derivativePath)) {
            try {
                $image = (new ImageManager(new Driver))
                    ->read($disk->path($sourcePath))
                    ->cover(self::WIDTH, self::HEIGHT);

                $disk->makeDirectory(dirname($derivativePath));
                $disk->put($derivativePath, (string) $image->toJpeg(self::QUALITY));
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        }

        return [
            'url' => url($disk->url($derivativePath)),
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'type' => 'image/jpeg',
        ];
    }
}
