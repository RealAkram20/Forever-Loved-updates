<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resellers had a single logo standing in for both themes, so a reseller whose logo is dark
 * artwork was invisible against the dark sidebar and dark-mode memorial pages — the platform
 * has had branding.logo_dark_path for exactly this reason since before the reseller program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('logo_dark_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn('logo_dark_path');
        });
    }
};
