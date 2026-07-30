<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When each account was last seen.
 *
 * Nothing recorded a sign-in before this, so "registered but never came back" — the single
 * most useful churn signal the reports have — was not computable at all. Written by a
 * listener on Laravel's Login event rather than by the six controllers that authenticate,
 * so a seventh sign-in path cannot silently skip it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Indexed: the dormant-account reports filter on it directly, and on a platform
            // with resellers this table is the largest one people report against.
            $table->timestamp('last_login_at')->nullable()->after('remember_token')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_login_at']);
            $table->dropColumn('last_login_at');
        });
    }
};
