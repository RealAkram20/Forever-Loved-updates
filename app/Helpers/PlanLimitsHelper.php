<?php

namespace App\Helpers;

use App\Models\Memorial;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\UserSubscription;
use App\Support\PlanFeatures;
use Illuminate\Support\Facades\Cache;

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
            return static::getFreePlan($memorial->reseller_id);
        }

        if ($memorial->subscriptionPlan) {
            return $memorial->subscriptionPlan;
        }

        return static::getFreePlan($memorial->reseller_id);
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

    /**
     * The plan that governs a memorial when it has none of its own, or its subscription
     * has lapsed. This decides what a memorial is allowed to do, so which row it resolves
     * to matters.
     *
     * Scoped by tenant: a reseller's own free tier governs their memorials, the platform's
     * governs the platform's. Unscoped, this returned whichever row the database happened
     * to yield first — so once resellers publish their own plans, a funeral home's limits
     * could silently start governing a direct platform memorial, or the reverse.
     *
     * A reseller without a free tier of their own falls back to the platform's, so
     * entitlements never resolve to null and lock a memorial out of its own features.
     */
    private static function getFreePlan(?int $resellerId = null): ?SubscriptionPlan
    {
        $lookup = function (?int $scope) {
            return SubscriptionPlan::where('is_active', true)
                ->when($scope === null,
                    fn ($q) => $q->whereNull('reseller_id'),
                    fn ($q) => $q->where('reseller_id', $scope))
                // An explicit 'free' slug wins; a zero price is the fallback signal.
                ->where(fn ($q) => $q->where('slug', 'free')->orWhere('price', 0))
                ->orderByRaw("CASE WHEN slug = 'free' THEN 0 ELSE 1 END")
                ->orderBy('sort_order')
                ->first();
        };

        return ($resellerId !== null ? $lookup($resellerId) : null) ?? $lookup(null);
    }

    /**
     * Apply one integer allowance, in the shape every caller of this class expects.
     *
     * The four checks below used to each carry their own copy of this, their own fallback
     * default, and the same inversion: `if ($max === 0) return allowed`. Zero meant
     * unlimited, so an admin withholding a feature by typing 0 granted it without limit.
     * -1 now says unlimited and 0 says none, and the two are decided here once.
     *
     * $count is a closure so that a plan of 0 or -1 never pays for the query — the count is
     * only wanted when there is a real ceiling to compare it against.
     */
    private static function applyLimit(int $max, callable $count, string $label): array
    {
        if (PlanFeatures::isUnlimited($max)) {
            return ['allowed' => true, 'current' => 0, 'max' => $max, 'reason' => null];
        }

        if ($max === PlanFeatures::NONE) {
            return [
                'allowed' => false,
                'current' => 0,
                'max' => 0,
                'reason' => ucfirst($label).' is not included in your current plan.',
            ];
        }

        $current = $count();

        return [
            'allowed' => $current < $max,
            'current' => $current,
            'max' => $max,
            'reason' => $current < $max ? null : "You have reached your plan's limit of {$max} {$label} ({$current}/{$max}).",
        ];
    }

    /** The plan's value for one allowance, or the catalogue default when there is no plan. */
    private static function limitFor(Memorial $memorial, string $key): int
    {
        $plan = static::getEffectivePlan($memorial);

        return (int) ($plan?->{$key} ?? PlanFeatures::defaultFor($key));
    }

    /**
     * Check if a gallery image can be uploaded.
     * Returns ['allowed' => bool, 'current' => int, 'max' => int, 'reason' => ?string].
     */
    public static function canUploadGalleryImage(Memorial $memorial): array
    {
        return static::applyLimit(
            static::limitFor($memorial, 'max_gallery_images'),
            fn () => static::galleryImageCount($memorial),
            'photos'
        );
    }

    /**
     * Check if a gallery video can be uploaded.
     */
    public static function canUploadGalleryVideo(Memorial $memorial): array
    {
        return static::applyLimit(
            static::limitFor($memorial, 'max_gallery_videos'),
            fn () => static::galleryVideoCount($memorial),
            'videos'
        );
    }

    /**
     * Check if a tribute can be added.
     *
     * The feature flag is asked first. Without it a plan could only withhold tributes by
     * setting the count to zero, which also stopped visitors writing stories — the two
     * share this quota.
     */
    public static function canAddTribute(Memorial $memorial): array
    {
        if (! static::canUseTributes($memorial)) {
            return [
                'allowed' => false,
                'current' => 0,
                'max' => 0,
                'reason' => 'Candle and flower tributes are not included in your current plan.',
            ];
        }

        return static::applyLimit(
            static::limitFor($memorial, 'max_tributes'),
            fn () => $memorial->tributes()->count(),
            'tributes'
        );
    }

    /**
     * Check if a story chapter can be added.
     */
    public static function canAddChapter(Memorial $memorial): array
    {
        return static::applyLimit(
            static::limitFor($memorial, 'max_chapters'),
            fn () => $memorial->storyChapters()->count(),
            'chapters'
        );
    }

    /**
     * Check if another person can be invited to help build the memorial.
     *
     * Only accepted collaborators count. An invitation nobody has taken up is not somebody
     * contributing, and charging for it would let a plan be exhausted by typos.
     */
    public static function canAddContributor(Memorial $memorial): array
    {
        return static::applyLimit(
            static::limitFor($memorial, 'max_contributors'),
            fn () => $memorial->collaborators()->whereNotNull('accepted_at')->count(),
            'contributors'
        );
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
     * Whether a file of this size can be stored.
     *
     * Both budgets were validated on the admin form and written to the database, and then
     * nothing in the codebase ever read them — a plan could say 100 MB and hold ten
     * gigabytes. Video carries its own budget on top of the overall one, because the
     * pricing sells video by how much of it you get rather than by file count, and it is
     * the only quantity here that can be measured exactly. Duration cannot: reading it needs
     * ffprobe on the host, and the browser's own figure is set by the person uploading.
     *
     * $type is the media type being added ('photo', 'video', 'music'), so the video budget
     * is only asked about video.
     */
    public static function canStore(Memorial $memorial, int $incomingBytes, string $type): array
    {
        if ($type === 'video') {
            $videoCheck = static::checkBudget(
                static::limitFor($memorial, 'max_video_storage_mb'),
                static::videoBytes($memorial),
                $incomingBytes,
                'video'
            );

            if (! $videoCheck['allowed']) {
                return $videoCheck;
            }
        }

        return static::checkBudget(
            static::limitFor($memorial, 'storage_limit_mb'),
            $memorial->storageBytes(),
            $incomingBytes,
            'storage'
        );
    }

    private static function checkBudget(int $maxMb, int $usedBytes, int $incomingBytes, string $label): array
    {
        if (PlanFeatures::isUnlimited($maxMb)) {
            return ['allowed' => true, 'reason' => null];
        }

        if ($maxMb === PlanFeatures::NONE) {
            return [
                'allowed' => false,
                'reason' => $label === 'video'
                    ? 'Video uploads are not included in your current plan.'
                    : 'Your current plan has no storage allowance.',
            ];
        }

        $maxBytes = $maxMb * 1024 * 1024;

        if ($usedBytes + $incomingBytes <= $maxBytes) {
            return ['allowed' => true, 'reason' => null];
        }

        return [
            'allowed' => false,
            'reason' => 'This would take you past your plan\'s '.$label.' allowance of '
                .static::formatMb($maxMb).'.',
        ];
    }

    private static function formatMb(int $mb): string
    {
        return $mb >= 1024
            ? rtrim(rtrim(number_format($mb / 1024, 1), '0'), '.').' GB'
            : $mb.' MB';
    }

    /** Bytes held by this memorial's videos, gallery and story alike. */
    private static function videoBytes(Memorial $memorial): int
    {
        return (int) $memorial->media()->where('type', 'video')->sum('size');
    }

    /**
     * Check if candle and flower tributes are offered at all.
     */
    public static function canUseTributes(Memorial $memorial): bool
    {
        $plan = static::getEffectivePlan($memorial);

        return (bool) ($plan?->feature_tributes ?? PlanFeatures::defaultFor('feature_tributes'));
    }

    /**
     * Check if the gallery may be sorted into browsable albums.
     */
    public static function canUseAlbums(Memorial $memorial): bool
    {
        $plan = static::getEffectivePlan($memorial);

        return (bool) ($plan?->feature_albums ?? PlanFeatures::defaultFor('feature_albums'));
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
        // Read off the catalogue rather than listed by hand, so a column added there is not
        // silently missing from everything that displays a plan.
        return collect(PlanFeatures::columns())
            ->mapWithKeys(fn (string $column) => [$column => $plan->{$column}])
            ->all();
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
            'contributors' => static::canAddContributor($memorial),
            'ai_bio' => static::canUseAiBio($memorial),
            'albums' => static::canUseAlbums($memorial),
            'can_use_tributes' => static::canUseTributes($memorial),
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
     *
     * Deferred to Memorial::galleryMedia() rather than restating the exclusion here. Two
     * reasons, and the second is the one that matters:
     *
     * The old form pulled every media id on the platform out of post_media with a pluck()
     * and inlined them into a NOT IN — so the cost of rendering a memorial grew with the
     * total content of the site, and this runs on every public page view via quotaInfo().
     * galleryMedia() does the same exclusion as a correlated NOT EXISTS the database can
     * index.
     *
     * And what a plan allows is now the same query as what the gallery shows, so a family
     * cannot be told they are at their limit while looking at fewer photos than that.
     */
    private static function galleryImageCount(Memorial $memorial): int
    {
        return $memorial->galleryMedia()->where('type', 'photo')->count();
    }

    /**
     * Count gallery videos (excluding those used in posts).
     */
    private static function galleryVideoCount(Memorial $memorial): int
    {
        return $memorial->galleryMedia()->where('type', 'video')->count();
    }
}
