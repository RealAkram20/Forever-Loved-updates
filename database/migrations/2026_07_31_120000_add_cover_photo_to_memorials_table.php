<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The banner behind the profile photo on the public memorial page.
     *
     * Size is stored alongside the path for the same reason profile_photo_size is: a cover
     * never gets a media row, so any storage sum that only looks at the media table would
     * miss it and under-report what a reseller is actually using.
     */
    public function up(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->string('cover_photo_path')->nullable()->after('profile_photo_size');
            $table->unsignedBigInteger('cover_photo_size')->nullable()->after('cover_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->dropColumn(['cover_photo_path', 'cover_photo_size']);
        });
    }
};
