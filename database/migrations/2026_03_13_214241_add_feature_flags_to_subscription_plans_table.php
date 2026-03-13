<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('feature_background_music')->default(false)->after('max_ai_bio_per_day');
            $table->boolean('feature_advanced_privacy')->default(false)->after('feature_background_music');
            $table->boolean('feature_guest_notifications')->default(false)->after('feature_advanced_privacy');
            $table->boolean('feature_never_expires')->default(false)->after('feature_guest_notifications');
            $table->boolean('feature_no_ads')->default(false)->after('feature_never_expires');
            $table->boolean('feature_share_memories')->default(false)->after('feature_no_ads');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'feature_background_music',
                'feature_advanced_privacy',
                'feature_guest_notifications',
                'feature_never_expires',
                'feature_no_ads',
                'feature_share_memories',
            ]);
        });
    }
};
