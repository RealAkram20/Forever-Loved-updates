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
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('custom_domain')->nullable()->unique()->after('slug');
            $table->string('custom_domain_token')->nullable()->after('custom_domain');
            $table->string('custom_domain_status')->default('unverified')->after('custom_domain_token');
            $table->timestamp('custom_domain_verified_at')->nullable()->after('custom_domain_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn(['custom_domain', 'custom_domain_token', 'custom_domain_status', 'custom_domain_verified_at']);
        });
    }
};
