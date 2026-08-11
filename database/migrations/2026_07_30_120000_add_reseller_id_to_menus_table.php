<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menus become per-tenant.
 *
 * `location` was unique across the whole table, so there was exactly one header menu, one
 * footer_quick and one footer_company for the entire platform — the ones an admin builds in
 * Settings → Menus, pointing at our About, Pricing and Contact. AppServiceProvider therefore
 * withheld them entirely on a reseller's own site rather than advertise us on their domain,
 * which left a white-labeled site with a one-item nav and its owner no way to change it.
 *
 * A nullable reseller_id keeps the platform's own menus exactly where they are (NULL), and
 * the unique key moves to (reseller_id, location) so each tenant gets their own set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
        });

        // Separate statement: the column has to exist before it can be part of the new key,
        // and SQLite rebuilds the table for each change rather than altering in place.
        Schema::table('menus', function (Blueprint $table) {
            $table->dropUnique('menus_location_unique');
            $table->unique(['reseller_id', 'location']);
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropUnique(['reseller_id', 'location']);
            $table->dropConstrainedForeignId('reseller_id');
        });

        Schema::table('menus', function (Blueprint $table) {
            $table->unique('location');
        });
    }
};
