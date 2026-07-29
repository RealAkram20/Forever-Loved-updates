<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Get the public URL for a file stored on the public disk.
     *
     * For HTTP requests, uses the incoming host/scheme (and subdirectory base path)
     * so logos and uploads work when APP_URL still points at localhost or another domain.
     * Falls back to url() for CLI/queue.
     */
    public static function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));

        if (! app()->runningInConsole() && request()->getHttpHost()) {
            $root = request()->getScheme().'://'.request()->getHttpHost();
            $base = rtrim(request()->getBasePath(), '/');

            return $root.$base.'/storage/'.$path;
        }

        return rtrim(config('app.url'), '/').'/storage/'.$path;
    }

    /**
     * Storage paths for organized structure:
     * - users/{user_id}/profile/     - user profile images
     * - memorials/{memorial_id}/profile/   - memorial profile photo
     * - memorials/{memorial_id}/gallery/   - gallery photos & videos
     * - memorials/{memorial_id}/posts/     - post media (images, videos, audio)
     */
    public static function userProfilePath(int $userId): string
    {
        return "users/{$userId}/profile";
    }

    public static function memorialProfilePath(int $memorialId): string
    {
        return "memorials/{$memorialId}/profile";
    }

    public static function memorialGalleryPath(int $memorialId): string
    {
        return "memorials/{$memorialId}/gallery";
    }

    public static function memorialPostsPath(int $memorialId): string
    {
        return "memorials/{$memorialId}/posts";
    }

    /**
     * Human-readable byte count. Binary units, matching how storage_limit_gb is converted
     * for comparison — showing decimal GB against a binary limit would make a reseller at
     * their cap look like they still had 7% left.
     */
    public static function formatBytes(?int $bytes, int $precision = 1): string
    {
        $bytes = max(0, (int) $bytes);

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units));
        $value = $bytes / 1024 ** $power;

        // No trailing ".0" on whole numbers, and never a fractional KB.
        $decimals = ($power === 1 || fmod($value, 1.0) === 0.0) ? 0 : $precision;

        return number_format($value, $decimals).' '.$units[$power - 1];
    }
}
