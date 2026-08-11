<?php

use App\Support\PlanFeatures;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The columns the new three-tier pricing needs, including for features not yet built.
 *
 * The unbuilt ones are deliberate: pricing has to be costed, configured and agreed before
 * the work starts, and a column that exists is the difference between an admin setting the
 * plan up today and a developer being asked for a schema change on launch day. Nothing a
 * customer sees reads them until PlanFeatures marks the entry available.
 *
 * Defaults preserve current behaviour rather than the new pricing — an existing plan must
 * not silently gain or lose anything because a column appeared. The new tiers arrive
 * through the seeder, where the values are visible and reviewable.
 */
return new class extends Migration
{
    /**
     * Column defaults, chosen to preserve what existing plans already allowed — *not* the
     * catalogue defaults, which describe what a new plan should offer.
     *
     * The two are different questions and conflating them would have broken the live site.
     * PlanFeatures::defaultFor('max_video_storage_mb') is NONE, because a new plan should
     * have to opt into video. Applied as a column default that would have landed on every
     * plan already in the database, including the one people are paying for, and stopped
     * every video upload the moment this migration ran. Contributors were never capped at
     * all before today, so a default of 5 would have locked out a family already past it.
     *
     * -1 is unlimited: a column appearing must never take away something that worked
     * yesterday. The new tiers get their real figures from the seeder, where they are
     * visible and reviewable.
     */
    private const LIMITS = [
        'max_contributors' => -1,
        'max_video_storage_mb' => -1,
    ];

    private const FLAGS = [
        'feature_albums',
        'feature_tributes',
        'feature_qr_code',
        'feature_funeral_details',
        'feature_livestream',
        'feature_order_of_service',
        'feature_timeline',
        'feature_anniversary_reminders',
    ];

    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            foreach (self::LIMITS as $column => $default) {
                $table->integer($column)->default($default);
            }

            foreach (self::FLAGS as $column) {
                $table->boolean($column)->default((bool) PlanFeatures::defaultFor($column));
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // array_keys: LIMITS is keyed by column name, so spreading it would hand
            // dropColumn the default values instead.
            $table->dropColumn([...array_keys(self::LIMITS), ...self::FLAGS]);
        });
    }
};
