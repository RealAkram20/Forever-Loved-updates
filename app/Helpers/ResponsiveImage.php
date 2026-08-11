<?php

namespace App\Helpers;

use App\Services\ImageDerivativeService;
use Illuminate\Support\HtmlString;

/**
 * The blade-side face of ImageDerivativeService: one call that renders the src /
 * srcset / sizes attribute set for an uploaded image, falling back to a plain src
 * of the original when no derivatives exist yet. Templates stay one line per image
 * and cannot half-adopt the ladder (srcset without sizes is how you ship the
 * largest rung to a phone).
 */
class ResponsiveImage
{
    /**
     * @param  string  $sizes  The layout's honest answer to "how wide will this render",
     *                         e.g. '(min-width: 640px) 33vw, 50vw' for a grid cell.
     */
    public static function attrs(?string $path, string $sizes): HtmlString
    {
        if ($path === null || $path === '') {
            return new HtmlString('');
        }

        $src = e((string) StorageHelper::publicUrl($path));

        $variants = app(ImageDerivativeService::class)->variants($path);

        if ($variants === null) {
            return new HtmlString('src="'.$src.'"');
        }

        return new HtmlString(
            'src="'.$src.'" srcset="'.e($variants['srcset']).'" sizes="'.e($sizes).'"'
        );
    }

    /** A single derivative URL at roughly this width — avatars, JS payloads, mails. */
    public static function url(?string $path, int $width): ?string
    {
        return app(ImageDerivativeService::class)->urlFor($path, $width);
    }
}
