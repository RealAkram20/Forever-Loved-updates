<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The entitlement that unlocks the reseller website / page builder, sitting alongside the
 * other per-tier capability flags (embedding, domain routing, business analytics). Off by
 * default: a tier grants it only once an admin turns it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_tiers', function (Blueprint $table) {
            $table->boolean('feature_page_builder')->default(false)->after('feature_business_analytics');
        });
    }

    public function down(): void
    {
        Schema::table('reseller_tiers', function (Blueprint $table) {
            $table->dropColumn('feature_page_builder');
        });
    }
};
