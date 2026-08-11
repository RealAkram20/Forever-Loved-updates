<?php

namespace App\Support;

use App\Models\SubscriptionPlan;

/**
 * What a plan is allowed to do, declared once.
 *
 * This list used to be written out by hand in six places — the admin plan screen, the
 * reseller one, the public pricing page, the pricing page-builder widget, and the validation
 * plus fillable arrays in two controllers. Adding a single feature meant six edits, and the
 * four screens had already begun to disagree with each other about how to render a limit.
 *
 * Declared in code rather than stored as rows, for the same reason StandardPages is: a
 * toggle with no gate behind it is a promise nobody keeps. Adding an entry here without
 * writing the check that reads it produces a switch that turns on nothing.
 *
 * `available` is what lets pricing be set up before a feature exists. An entry marked false
 * appears in the admin form — so the plans can be priced and configured today — and is left
 * out of everything a customer sees, until the day the feature ships and the flag flips.
 */
class PlanFeatures
{
    /** An integer allowance. UNLIMITED for no ceiling, NONE for not available at all. */
    public const TYPE_LIMIT = 'limit';

    /** A straight on/off feature flag. */
    public const TYPE_BOOL = 'bool';

    /** An integer number of megabytes, rendered as MB or GB. */
    public const TYPE_STORAGE = 'storage';

    /** A per-day allowance, where zero means the feature is off. */
    public const TYPE_DAILY = 'daily';

    /**
     * A row with no column behind it — always true, on every plan.
     *
     * The comparison table has to be able to say "Beautiful Memorial Page" and "Secure
     * Hosting & Backups" without somebody inventing a switch for them. They are not
     * features that can be withheld; they are what the product is.
     */
    public const TYPE_ALWAYS = 'always';

    /**
     * -1, not 0.
     *
     * Zero used to mean unlimited, which made "this plan gets none of that" impossible to
     * express: setting a free plan's video allowance to 0 to withhold video granted
     * unlimited video instead. Now the two say different things, and the dangerous one is
     * not the one an admin reaches for by accident.
     */
    public const UNLIMITED = -1;
    public const NONE = 0;

    /**
     * key => definition.
     *
     * `default` is what a memorial gets when it has no plan at all, which is also the
     * column default. It lives here so there is a single answer, rather than the different
     * one each method in PlanLimitsHelper used to carry in its own `?? 10`.
     *
     * @return array<string, array{label: string, type: string, default: int|bool, available: bool, group: string, help: string}>
     */
    public static function catalogue(): array
    {
        return [
            // ── What the product is ──────────────────────────────────────────
            'memorial_page' => [
                'label' => 'Beautiful Memorial Page',
                'type' => self::TYPE_ALWAYS,
                'default' => true,
                'available' => true,
                'group' => 'Included',
                'help' => '',
            ],
            'life_story' => [
                'label' => 'Life Story / Obituary',
                'type' => self::TYPE_ALWAYS,
                'default' => true,
                'available' => true,
                'group' => 'Included',
                'help' => '',
            ],
            'secure_hosting' => [
                'label' => 'Secure Hosting & Backups',
                'type' => self::TYPE_ALWAYS,
                'default' => true,
                'available' => true,
                'group' => 'Included',
                'help' => '',
            ],

            // ── Allowances ───────────────────────────────────────────────────
            'memorial_limit' => [
                'label' => 'Memorials',
                'type' => self::TYPE_LIMIT,
                'default' => 1,
                'available' => true,
                'group' => 'Allowances',
                'help' => 'How many memorials this plan covers.',
            ],
            'max_gallery_images' => [
                'label' => 'Photo Uploads',
                'type' => self::TYPE_LIMIT,
                'default' => 5,
                'available' => true,
                'group' => 'Allowances',
                'help' => '',
            ],
            'max_gallery_videos' => [
                'label' => 'Video Uploads',
                'type' => self::TYPE_LIMIT,
                'default' => self::NONE,
                'available' => true,
                'group' => 'Allowances',
                'help' => 'How many videos. The total size they may take up is set separately below.',
            ],
            'max_video_storage_mb' => [
                'label' => 'Video Allowance',
                'type' => self::TYPE_STORAGE,
                'default' => self::NONE,
                'available' => true,
                'group' => 'Allowances',
                'help' => 'Roughly 1.2 GB per hour of phone video. Measured exactly, unlike duration, which the browser reports and can be under-stated.',
            ],
            'storage_limit_mb' => [
                'label' => 'Total Storage',
                'type' => self::TYPE_STORAGE,
                'default' => 100,
                'available' => true,
                'group' => 'Allowances',
                'help' => 'Everything on the memorial — photos, videos, audio, cover and profile pictures.',
            ],
            'max_contributors' => [
                'label' => 'Family & Friends Contributors',
                'type' => self::TYPE_LIMIT,
                'default' => 5,
                'available' => true,
                'group' => 'Allowances',
                'help' => 'People invited to help build the memorial. Visitors leaving tributes are not counted.',
            ],
            'max_tributes' => [
                'label' => 'Tributes',
                'type' => self::TYPE_LIMIT,
                'default' => 20,
                'available' => true,
                'group' => 'Allowances',
                'help' => '',
            ],
            'max_chapters' => [
                'label' => 'Story Chapters',
                'type' => self::TYPE_LIMIT,
                'default' => 3,
                'available' => true,
                'group' => 'Allowances',
                'help' => '',
            ],
            'max_ai_bio_per_day' => [
                'label' => 'AI Biography',
                'type' => self::TYPE_DAILY,
                'default' => self::NONE,
                'available' => true,
                'group' => 'Allowances',
                'help' => 'Generations per day. Zero switches it off.',
            ],

            // ── Features that exist today ────────────────────────────────────
            'feature_albums' => [
                'label' => 'Photo and Video Albums',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => true,
                'group' => 'Features',
                'help' => 'Lets the family sort the gallery into categories visitors can browse.',
            ],
            'feature_tributes' => [
                'label' => 'Candle & Flower Tributes',
                'type' => self::TYPE_BOOL,
                'default' => true,
                'available' => true,
                'group' => 'Features',
                'help' => 'The one-tap gestures visitors leave. Off hides the cards entirely.',
            ],
            'feature_background_music' => [
                'label' => 'Background Music',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => true,
                'group' => 'Features',
                'help' => '',
            ],
            'feature_share_memories' => [
                'label' => 'Easy Sharing',
                'type' => self::TYPE_BOOL,
                'default' => true,
                'available' => true,
                'group' => 'Features',
                'help' => '',
            ],
            'feature_advanced_privacy' => [
                'label' => 'Advanced Privacy',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => true,
                'group' => 'Features',
                'help' => 'Required before contributors can be invited at all.',
            ],
            'feature_guest_notifications' => [
                'label' => 'Guest Notifications',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => true,
                'group' => 'Features',
                'help' => '',
            ],
            'feature_no_ads' => [
                'label' => 'Ad-Free',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => true,
                'group' => 'Features',
                'help' => '',
            ],
            'feature_never_expires' => [
                'label' => 'Permanent Preservation',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => true,
                'group' => 'Features',
                'help' => 'The memorial is never expired by the subscription lifecycle.',
            ],

            // ── Priced now, built later ──────────────────────────────────────
            // Each of these is switchable in the admin screen so the plans can be costed
            // and configured today, and stays out of the public pricing page until the
            // feature behind it actually exists. Flipping `available` publishes the row.
            'feature_qr_code' => [
                'label' => 'QR Code',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => false,
                'group' => 'Coming',
                'help' => 'Not built yet.',
            ],
            'feature_funeral_details' => [
                'label' => 'Funeral Service Information',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => false,
                'group' => 'Coming',
                'help' => 'Not built yet.',
            ],
            'feature_livestream' => [
                'label' => 'Livestream Link',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => false,
                'group' => 'Coming',
                'help' => 'Not built yet.',
            ],
            'feature_order_of_service' => [
                'label' => 'Upload Order of Service',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => false,
                'group' => 'Coming',
                'help' => 'Not built yet.',
            ],
            'feature_timeline' => [
                'label' => 'Timeline of Life',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => false,
                'group' => 'Coming',
                'help' => 'Not built yet.',
            ],
            'feature_anniversary_reminders' => [
                'label' => 'Anniversary Reminders',
                'type' => self::TYPE_BOOL,
                'default' => false,
                'available' => false,
                'group' => 'Coming',
                'help' => 'Not built yet.',
            ],
        ];
    }

    /**
     * Entries backed by a real column — everything except the always-on marketing rows.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function stored(): array
    {
        return array_filter(
            self::catalogue(),
            fn (array $definition) => $definition['type'] !== self::TYPE_ALWAYS
        );
    }

    /** Column names, for $fillable and for the two plan controllers' update lists. */
    public static function columns(): array
    {
        return array_keys(self::stored());
    }

    /**
     * What a customer is allowed to be shown. Anything not yet built is withheld: a plan
     * page that promises a QR code the product cannot produce is a refund conversation, and
     * on a lifetime plan it is a promise with no end date.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function publicRows(): array
    {
        return array_filter(
            self::catalogue(),
            fn (array $definition) => $definition['available']
        );
    }

    /** Grouped for the admin form, in catalogue order within each group. */
    public static function storedByGroup(): array
    {
        $grouped = [];

        foreach (self::stored() as $key => $definition) {
            $grouped[$definition['group']][$key] = $definition;
        }

        return $grouped;
    }

    public static function definition(string $key): ?array
    {
        return self::catalogue()[$key] ?? null;
    }

    /** Validation rules for the whole set, for both plan controllers. */
    public static function rules(): array
    {
        $rules = [];

        foreach (self::stored() as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                self::TYPE_BOOL => ['boolean'],
                // min:-1 rather than min:0 — -1 is the unlimited sentinel, and anything
                // below it is a typo, not a bigger allowance.
                self::TYPE_LIMIT => ['required', 'integer', 'min:-1'],
                self::TYPE_STORAGE => ['required', 'integer', 'min:-1'],
                self::TYPE_DAILY => ['required', 'integer', 'min:0'],
                default => ['nullable'],
            };
        }

        return $rules;
    }

    /** The column default, used by the migration and by a memorial with no plan at all. */
    public static function defaultFor(string $key): int|bool
    {
        return self::definition($key)['default'] ?? self::NONE;
    }

    public static function isUnlimited(int $value): bool
    {
        return $value < 0;
    }

    /**
     * How a plan's value for one entry should read on screen.
     *
     * Every screen used to write its own version of this, and they disagreed: the admin
     * list rendered 0 as "∞", the pricing table rendered it as "Unlimited", and neither had
     * any way to say "not included".
     */
    public static function format(?SubscriptionPlan $plan, string $key): string
    {
        $definition = self::definition($key);

        if (! $definition) {
            return '—';
        }

        if ($definition['type'] === self::TYPE_ALWAYS) {
            return '✓';
        }

        $value = $plan?->{$key} ?? $definition['default'];

        return match ($definition['type']) {
            self::TYPE_BOOL => $value ? '✓' : '—',
            self::TYPE_DAILY => (int) $value > 0 ? $value.'/day' : '—',
            self::TYPE_STORAGE => self::formatStorage((int) $value),
            default => self::formatLimit((int) $value),
        };
    }

    private static function formatLimit(int $value): string
    {
        if (self::isUnlimited($value)) {
            return 'Unlimited';
        }

        return $value === self::NONE ? '—' : (string) $value;
    }

    private static function formatStorage(int $mb): string
    {
        if (self::isUnlimited($mb)) {
            return 'Unlimited';
        }

        if ($mb === self::NONE) {
            return '—';
        }

        return $mb >= 1024
            ? rtrim(rtrim(number_format($mb / 1024, 1), '0'), '.').' GB'
            : $mb.' MB';
    }
}
