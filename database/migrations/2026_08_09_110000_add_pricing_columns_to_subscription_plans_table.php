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
    private const LIMITS = [
        'max_contributors',
        'max_video_storage_mb',
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
            foreach (self::LIMITS as $column) {
                $table->integer($column)->default((int) PlanFeatures::defaultFor($column));
            }

            foreach (self::FLAGS as $column) {
                $table->boolean($column)->default((bool) PlanFeatures::defaultFor($column));
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([...self::LIMITS, ...self::FLAGS]);
        });
    }
};
