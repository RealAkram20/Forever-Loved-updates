<?php

namespace App\Helpers;

use App\Models\Memorial;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlanLimitsHelper
{
    /**
     * Resolve the effective plan for a memorial.
     * When the subscription is overdue, falls back to the free plan
     * so all premium features are automatically locked.
     */
    public static function getEffectivePlan(Memorial $memorial): ?SubscriptionPlan
    {
        if (static::isSubscriptionOverdue($memorial)) {
            return static::getFreePlan();
        }

        if ($memorial->subscriptionPlan) {
            return $memorial->subscriptionPlan;
        }

        return static::getFreePlan();
    }

    /**
     * Check if the memorial's subscription is overdue (expired and not renewed).
     */
    public static function isSubscriptionOverdue(Memorial $memorial): bool
    {
        $sub = $memorial->userSubscription;
        if (! $sub) {
            return false;
        }

        return $sub->status === 'overdue';
    }

    /**
     * Check whether media operations (add, edit, delete) are allowed.
     * Blocked when subscription is overdue.
     */
    public static function canModifyMedia(Memorial $memorial): array
    {
        if (static::isSubscriptionOverdue($memorial)) {
            return [
                'allowed' => false,
                'reason' => 'Your subscription is overdue. Renew your plan to add, edit, or delete media and access premium features.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    private static function getFreePlan(): ?SubscriptionPlan
    {
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
     * Applies both the subscription plan limit and the admin global limits.
     */
    public static function canUseAiBio(Memorial $memorial): array
    {
        $plan = static::getEffectivePlan($memorial);
        $planMax = $plan?->max_ai_bio_per_day ?? 0;

        if ($planMax === 0) {
            return [
                'allowed' => false,
                'current' => 0,
                'max' => 0,
                'reason' => 'AI biography generation is not available on your current plan.',
            ];
        }

        $globalDaily = (int) SystemSetting::get('ai.max_requests_per_user_per_day', 0);
        $globalMonthly = (int) SystemSetting::get('ai.max_requests_per_user_per_month', 0);
        $effectiveDaily = ($globalDaily > 0) ? min($planMax, $globalDaily) : $planMax;

        $userId = $memorial->user_id;
        $dailyKey = "ai_bio:{$userId}:" . now()->format('Y-m-d');
        $currentDaily = (int) Cache::get($dailyKey, 0);

        if ($currentDaily >= $effectiveDaily) {
            return [
                'allowed' => false,
                'current' => $currentDaily,
                'max' => $effectiveDaily,
                'reason' => "Daily AI biography limit reached ({$currentDaily}/{$effectiveDaily}). Try again tomorrow.",
            ];
        }

        if ($globalMonthly > 0) {
            $monthlyKey = "ai_bio_month:{$userId}:" . now()->format('Y-m');
            $currentMonthly = (int) Cache::get($monthlyKey, 0);
            if ($currentMonthly >= $globalMonthly) {
                return [
                    'allowed' => false,
                    'current' => $currentDaily,
                    'max' => $effectiveDaily,
                    'reason' => "Monthly AI biography limit reached ({$currentMonthly}/{$globalMonthly}). Try again next month.",
                ];
            }
        }

        return [
            'allowed' => true,
            'current' => $currentDaily,
            'max' => $effectiveDaily,
            'reason' => null,
        ];
    }

    /**
     * Atomically reserve one AI bio usage slot. Increments first, then checks.
     * Returns quota info with 'allowed'. If over-limit, decrements back.
     */
    public static function reserveAiBioUsage(Memorial $memorial): array
    {
        $plan = static::getEffectivePlan($memorial);
        $planMax = $plan?->max_ai_bio_per_day ?? 0;

        if ($planMax === 0) {
            return [
                'allowed' => false,
                'current' => 0,
                'max' => 0,
                'reason' => 'AI biography generation is not available on your current plan.',
            ];
        }

        $globalDaily = (int) SystemSetting::get('ai.max_requests_per_user_per_day', 0);
        $globalMonthly = (int) SystemSetting::get('ai.max_requests_per_user_per_month', 0);
        $effectiveDaily = ($globalDaily > 0) ? min($planMax, $globalDaily) : $planMax;

        $userId = $memorial->user_id;
        $dailyKey = "ai_bio:{$userId}:" . now()->format('Y-m-d');
        $dailyExpiry = now()->endOfDay();

        if (!Cache::has($dailyKey)) {
            Cache::put($dailyKey, 0, $dailyExpiry);
        }
        $newDaily = (int) Cache::increment($dailyKey);

        if ($newDaily > $effectiveDaily) {
            Cache::decrement($dailyKey);
            return [
                'allowed' => false,
                'current' => $newDaily - 1,
                'max' => $effectiveDaily,
                'reason' => "Daily AI biography limit reached. Try again tomorrow.",
            ];
        }

        if ($globalMonthly > 0) {
            $monthlyKey = "ai_bio_month:{$userId}:" . now()->format('Y-m');
            $monthlyExpiry = now()->endOfMonth()->endOfDay();
            if (!Cache::has($monthlyKey)) {
                Cache::put($monthlyKey, 0, $monthlyExpiry);
            }
            $newMonthly = (int) Cache::increment($monthlyKey);
            if ($newMonthly > $globalMonthly) {
                Cache::decrement($monthlyKey);
                Cache::decrement($dailyKey);
                return [
                    'allowed' => false,
                    'current' => $newDaily - 1,
                    'max' => $effectiveDaily,
                    'reason' => "Monthly AI biography limit reached. Try again next month.",
                ];
            }
        }

        return [
            'allowed' => true,
            'current' => $newDaily,
            'max' => $effectiveDaily,
            'reason' => null,
        ];
    }

    /**
     * Give back a slot reserved by reserveAiBioUsage() when generation
     * produced nothing usable — a failed request must not consume quota.
     */
    public static function releaseAiBioUsage(Memorial $memorial): void
    {
        $userId = $memorial->user_id;

        $dailyKey = "ai_bio:{$userId}:" . now()->format('Y-m-d');
        if ((int) Cache::get($dailyKey, 0) > 0) {
            Cache::decrement($dailyKey);
        }

        $globalMonthly = (int) SystemSetting::get('ai.max_requests_per_user_per_month', 0);
        if ($globalMonthly > 0) {
            $monthlyKey = "ai_bio_month:{$userId}:" . now()->format('Y-m');
            if ((int) Cache::get($monthlyKey, 0) > 0) {
                Cache::decrement($monthlyKey);
            }
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
            'subscription_overdue' => static::isSubscriptionOverdue($memorial),
            'can_modify_media' => static::canModifyMedia($memorial),
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
