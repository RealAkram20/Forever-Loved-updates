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
            $table->unsignedInteger('max_gallery_images')->default(10)->after('storage_limit_mb');
            $table->unsignedInteger('max_gallery_videos')->default(2)->after('max_gallery_images');
            $table->unsignedInteger('max_tributes')->default(20)->after('max_gallery_videos');
            $table->unsignedInteger('max_chapters')->default(3)->after('max_tributes');
            $table->unsignedInteger('max_ai_bio_per_day')->default(0)->after('max_chapters');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'max_gallery_images',
                'max_gallery_videos',
                'max_tributes',
                'max_chapters',
                'max_ai_bio_per_day',
            ]);
        });
    }
};
