<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Get the public URL for a file stored on the public disk.
     * Uses the current request URL so images work regardless of APP_URL.
     */
    public static function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return url('/storage/' . ltrim($path, '/'));
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
}
