<?php

namespace App\Helpers;

use App\Models\Memorial;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlanLimitsHelper
{
    /**
     * Resolve the effective plan for a memorial.
     * Falls back to the default free plan if none is assigned.
     */
    public static function getEffectivePlan(Memorial $memorial): ?SubscriptionPlan
    {
        if ($memorial->subscriptionPlan) {
            return $memorial->subscriptionPlan;
        }

        return SubscriptionPlan::where('slug', 'free')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if a gallery image can be uploaded.
     * Returns ['allowed' => bool, 'current' => int, 'max' => int].
     */
    public static function canUploadGalleryImage(Memorial $memorial): array
    {
        $plan = static::getEffectivePlan($memorial);
        $max = $plan?->max_gallery_images ?? 10;

        if ($max === 0) {
            return ['allowed' => true, 'current' => 0, 'max' => 0];
        }

        $current = static::galleryImageCount($memorial);

        return [
            'allowed' => $current < $max,
            'current' => $current,
            'max' => $max,
        ];
    }

    /**
     * Check if a gallery video can be uploaded.
     */
    public static function canUploadGalleryVideo(Memorial $memorial): array
    {
        $plan = static::getEffectivePlan($memorial);
        $max = $plan?->max_gallery_videos ?? 2;

        if ($max === 0) {
            return ['allowed' => true, 'current' => 0, 'max' => 0];
        }

        $current = static::galleryVideoCount($memorial);

        return [
            'allowed' => $current < $max,
            'current' => $current,
            'max' => $max,
        ];
    }

    /**
     * Check if a tribute can be added.
     */
    public static function canAddTribute(Memorial $memorial): array
    {
        $plan = static::getEffectivePlan($memorial);
        $max = $plan?->max_tributes ?? 20;

        if ($max === 0) {
            return ['allowed' => true, 'current' => 0, 'max' => 0];
        }

        $current = $memorial->tributes()->count();

        return [
            'allowed' => $current < $max,
            'current' => $current,
            'max' => $max,
        ];
    }

    /**
     * Check if a story chapter can be added.
     */
    public static function canAddChapter(Memorial $memorial): array
    {
        $plan = static::getEffectivePlan($memorial);
        $max = $plan?->max_chapters ?? 3;

        if ($max === 0) {
            return ['allowed' => true, 'current' => 0, 'max' => 0];
        }

        $current = $memorial->storyChapters()->count();

        return [
            'allowed' => $current < $max,
            'current' => $current,
            'max' => $max,
        ];
    }

    /**
     * Check if AI bio generation can be used today for the memorial's owner.
     */
    public static function canUseAiBio(Memorial $memorial): array
    {
        $plan = static::getEffectivePlan($memorial);
        $max = $plan?->max_ai_bio_per_day ?? 0;

        if ($max === 0) {
            return [
                'allowed' => false,
                'current' => 0,
                'max' => 0,
                'reason' => 'AI biography generation is not available on your current plan.',
            ];
        }

        $userId = $memorial->user_id;
        $cacheKey = "ai_bio:{$userId}:" . now()->format('Y-m-d');
        $current = (int) Cache::get($cacheKey, 0);

        return [
            'allowed' => $current < $max,
            'current' => $current,
            'max' => $max,
            'reason' => $current >= $max
                ? "Daily AI biography limit reached ({$current}/{$max}). Try again tomorrow."
                : null,
        ];
    }

    /**
     * Increment the AI bio usage counter for today.
     */
    public static function incrementAiBioUsage(int $userId): void
    {
        $cacheKey = "ai_bio:{$userId}:" . now()->format('Y-m-d');
        $ttl = now()->endOfDay()->diffInSeconds(now());

        if (Cache::has($cacheKey)) {
            Cache::increment($cacheKey);
        } else {
            Cache::put($cacheKey, 1, $ttl);
        }
    }

    /**
     * Check if background music is allowed by the plan.
     */
    public static function canUseBackgroundMusic(Memorial $memorial): bool
    {
        $plan = static::getEffectivePlan($memorial);
        return (bool) ($plan?->feature_background_music ?? false);
    }

    /**
     * Check if advanced privacy (invite collaborators) is allowed.
     */
    public static function canUseAdvancedPrivacy(Memorial $memorial): bool
    {
        $plan = static::getEffectivePlan($memorial);
        return (bool) ($plan?->feature_advanced_privacy ?? false);
    }

    /**
     * Check if guest notifications (subscribe to updates) is allowed.
     */
    public static function canUseGuestNotifications(Memorial $memorial): bool
    {
        $plan = static::getEffectivePlan($memorial);
        return (bool) ($plan?->feature_guest_notifications ?? false);
    }

    /**
     * Check if the memorial never expires.
     */
    public static function hasNeverExpires(Memorial $memorial): bool
    {
        $plan = static::getEffectivePlan($memorial);
        return (bool) ($plan?->feature_never_expires ?? false);
    }

    /**
     * Check if the memorial is ad-free.
     */
    public static function hasNoAds(Memorial $memorial): bool
    {
        $plan = static::getEffectivePlan($memorial);
        return (bool) ($plan?->feature_no_ads ?? false);
    }

    /**
     * Check if sharing memories is allowed.
     */
    public static function canShareMemories(Memorial $memorial): bool
    {
        $plan = static::getEffectivePlan($memorial);
        return (bool) ($plan?->feature_share_memories ?? false);
    }

    /**
     * Get all limits as a structured array for display.
     */
    public static function getLimitsForPlan(SubscriptionPlan $plan): array
    {
        return [
            'max_gallery_images' => $plan->max_gallery_images,
            'max_gallery_videos' => $plan->max_gallery_videos,
            'max_tributes' => $plan->max_tributes,
            'max_chapters' => $plan->max_chapters,
            'max_ai_bio_per_day' => $plan->max_ai_bio_per_day,
            'feature_background_music' => $plan->feature_background_music,
            'feature_advanced_privacy' => $plan->feature_advanced_privacy,
            'feature_guest_notifications' => $plan->feature_guest_notifications,
            'feature_never_expires' => $plan->feature_never_expires,
            'feature_no_ads' => $plan->feature_no_ads,
            'feature_share_memories' => $plan->feature_share_memories,
        ];
    }

    /**
     * Get all quota info for a memorial (for UI display).
     */
    public static function getQuotaInfo(Memorial $memorial): array
    {
        return [
            'gallery_images' => static::canUploadGalleryImage($memorial),
            'gallery_videos' => static::canUploadGalleryVideo($memorial),
            'tributes' => static::canAddTribute($memorial),
            'chapters' => static::canAddChapter($memorial),
            'ai_bio' => static::canUseAiBio($memorial),
            'background_music' => static::canUseBackgroundMusic($memorial),
            'advanced_privacy' => static::canUseAdvancedPrivacy($memorial),
            'guest_notifications' => static::canUseGuestNotifications($memorial),
            'never_expires' => static::hasNeverExpires($memorial),
            'no_ads' => static::hasNoAds($memorial),
            'share_memories' => static::canShareMemories($memorial),
        ];
    }

    /**
     * Count gallery images (excluding those used in posts).
     */
    private static function galleryImageCount(Memorial $memorial): int
    {
        $usedInPosts = DB::table('post_media')->pluck('media_id')->toArray();

        return $memorial->media()
            ->where('type', 'photo')
            ->when(!empty($usedInPosts), fn ($q) => $q->whereNotIn('id', $usedInPosts))
            ->count();
    }

    /**
     * Count gallery videos (excluding those used in posts).
     */
    private static function galleryVideoCount(Memorial $memorial): int
    {
        $usedInPosts = DB::table('post_media')->pluck('media_id')->toArray();

        return $memorial->media()
            ->where('type', 'video')
            ->when(!empty($usedInPosts), fn ($q) => $q->whereNotIn('id', $usedInPosts))
            ->count();
    }
}
