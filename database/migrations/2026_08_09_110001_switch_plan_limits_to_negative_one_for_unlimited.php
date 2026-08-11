<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zero used to mean unlimited. Now -1 does, and zero means none.
 *
 * The old encoding could not express "this plan does not include that at all", and the way
 * it failed was the worst available: an admin withholding video from the free plan would
 * naturally type 0, and PlanLimitsHelper read 0 as *no ceiling* and granted unlimited video.
 * Every limit had the same inversion waiting in it.
 *
 * Only the five columns that carried the old sentinel are rewritten. max_ai_bio_per_day is
 * left exactly as it is: it always treated 0 as "off", which is the new meaning and was the
 * one column that had it right. storage_limit_mb never had a sentinel — its validation
 * floor was 10 — so a 0 there is data, not a flag.
 */
return new class extends Migration
{
    private const SENTINEL_COLUMNS = [
        'memorial_limit',
        'max_gallery_images',
        'max_gallery_videos',
        'max_tributes',
        'max_chapters',
    ];

    public function up(): void
    {
        // Every one of these was declared unsigned, so the new sentinel does not physically
        // fit until the column is widened to accept it. The type change has to land before
        // the data rewrite, not alongside it.
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('memorial_limit')->default(1)->change();
            $table->bigInteger('storage_limit_mb')->default(100)->change();
            $table->integer('max_gallery_images')->default(10)->change();
            $table->integer('max_gallery_videos')->default(2)->change();
            $table->integer('max_tributes')->default(20)->change();
            $table->integer('max_chapters')->default(3)->change();
            // max_ai_bio_per_day stays unsigned on purpose: 0 already means off there, and
            // it has no use for a negative value.
        });

        foreach (self::SENTINEL_COLUMNS as $column) {
            DB::table('subscription_plans')->where($column, 0)->update([$column => -1]);
        }
    }

    /**
     * Reversible, because the rewrite is a pure relabelling of the same intent — a plan that
     * meant unlimited before means unlimited after. A plan deliberately set to 0 (none)
     * under the new scheme would come back as unlimited on rollback, which is why the
     * seeder's new tiers are the only rows expected to hold a real 0.
     */
    public function down(): void
    {
        // storage_limit_mb is not in SENTINEL_COLUMNS — it had no sentinel to rewrite on the
        // way up, because 0 there was always a real figure rather than a flag. It can hold
        // -1 now though, so it still has to be cleared before the column narrows back to
        // unsigned, or rolling back fails on any plan offering unlimited storage.
        foreach ([...self::SENTINEL_COLUMNS, 'storage_limit_mb'] as $column) {
            DB::table('subscription_plans')->where($column, -1)->update([$column => 0]);
        }

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('memorial_limit')->default(1)->change();
            $table->unsignedBigInteger('storage_limit_mb')->default(100)->change();
            $table->unsignedInteger('max_gallery_images')->default(10)->change();
            $table->unsignedInteger('max_gallery_videos')->default(2)->change();
            $table->unsignedInteger('max_tributes')->default(20)->change();
            $table->unsignedInteger('max_chapters')->default(3)->change();
        });
    }
};
