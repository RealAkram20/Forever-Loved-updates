<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Scaled WebP copies of every image a family uploads.
 *
 * Uploads are stored exactly as they arrive — that file is the family's and stays
 * untouched. But serving it to every visitor meant a 566KB photo behind a 40px
 * avatar, and a memorial page that weighed five megabytes. Each upload therefore
 * gets a ladder of WebP derivatives, and the page offers the ladder via srcset so
 * the browser takes the cheapest rung that looks right.
 *
 * Derivatives live in a d/ folder beside the original, named after the full
 * original filename ("photo.jpg-480.webp") so two originals differing only by
 * extension cannot collide. Everything here is derived state: safe to delete, safe
 * to regenerate, and every consumer falls back to the original when a derivative
 * does not (yet) exist — a fresh upload is visible immediately and gets cheap a
 * minute later when the queued job lands.
 */
class ImageDerivativeService
{
    /** Thumb/avatar, grid cell, on-page display, lightbox. */
    public const WIDTHS = [160, 480, 960, 1600];

    private const QUALITY = 78;

    /** Formats worth scaling. GIFs are excluded: scaling flattens an animation. */
    private const SOURCE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function eligible(?string $path): bool
    {
        return $path !== null
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::SOURCE_EXTENSIONS, true);
    }

    /**
     * Make every missing derivative for one stored image. Returns the disk paths that
     * now exist (made now or already present); an unreadable or non-image source
     * simply yields none.
     *
     * @return list<string>
     */
    public function generate(string $path): array
    {
        $disk = Storage::disk('public');

        if (! self::eligible($path) || ! $disk->exists($path)) {
            return [];
        }

        try {
            $image = (new ImageManager(new Driver))->read($disk->path($path));
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $sourceWidth = $image->width();
        $made = [];

        foreach (self::WIDTHS as $width) {
            // Upscaling buys pixels that carry no detail and cost real bytes.
            if ($width > $sourceWidth) {
                break;
            }

            $derivative = self::derivativePath($path, $width);

            if (! $disk->exists($derivative)) {
                try {
                    $scaled = (clone $image)->scaleDown(width: $width);
                    $disk->makeDirectory(dirname($derivative));
                    $disk->put($derivative, (string) (function_exists('imagewebp')
                        ? $scaled->toWebp(self::QUALITY)
                        : $scaled->toJpeg(self::QUALITY)));
                } catch (\Throwable $e) {
                    report($e);

                    continue;
                }
            }

            $made[] = $derivative;
        }

        return $made;
    }

    /**
     * The srcset ladder for one stored image: only the rungs that actually exist on
     * disk, so a not-yet-processed upload degrades to its original rather than to a
     * broken image.
     *
     * @return array{srcset: string, widths: list<int>}|null null when no derivative exists yet.
     */
    public function variants(?string $path): ?array
    {
        if (! self::eligible($path)) {
            return null;
        }

        $disk = Storage::disk('public');
        $entries = [];
        $widths = [];

        foreach (self::WIDTHS as $width) {
            $derivative = self::derivativePath($path, $width);
            if ($disk->exists($derivative)) {
                $entries[] = url($disk->url($derivative)).' '.$width.'w';
                $widths[] = $width;
            }
        }

        if ($entries === []) {
            return null;
        }

        return ['srcset' => implode(', ', $entries), 'widths' => $widths];
    }

    /**
     * One URL at (or nearest above) the wanted width — for the places that take a
     * single src rather than a srcset: avatars, JS-built lightboxes, notification
     * payloads. Falls back to the original when nothing derived exists.
     */
    public function urlFor(?string $path, int $wanted): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (self::eligible($path)) {
            $best = null;
            foreach (self::WIDTHS as $width) {
                $derivative = self::derivativePath($path, $width);
                if ($disk->exists($derivative)) {
                    $best = $derivative;
                    if ($width >= $wanted) {
                        break;
                    }
                }
            }
            if ($best !== null) {
                return url($disk->url($best));
            }
        }

        return url($disk->url($path));
    }

    /** Remove a deleted image's derivatives, so the d/ folder never outlives its source. */
    public function delete(string $path): void
    {
        $disk = Storage::disk('public');

        foreach (self::WIDTHS as $width) {
            $derivative = self::derivativePath($path, $width);
            if ($disk->exists($derivative)) {
                $disk->delete($derivative);
            }
        }
    }

    public static function derivativePath(string $path, int $width): string
    {
        return dirname($path).'/d/'.basename($path).'-'.$width.'.webp';
    }
}
