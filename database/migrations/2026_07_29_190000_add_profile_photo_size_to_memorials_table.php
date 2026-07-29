<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile photos are stored straight to disk and referenced by profile_photo_path — they
 * never get a media row, so summing media.size undercounts every memorial by up to 5MB.
 * Recording the size here makes disk usage measurable, which is the prerequisite for
 * enforcing either the reseller tier's storage_limit_gb or a plan's storage_limit_mb
 * (neither of which is enforced today).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_photo_size')->nullable()->after('profile_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->dropColumn('profile_photo_size');
        });
    }
};
