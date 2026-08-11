<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages become tenant-scoped: a null reseller_id is a platform page (every page that
 * existed before this migration), a non-null one belongs to that reseller's own site.
 *
 * The old global `slug` unique is replaced by a composite (reseller_id, slug) unique so
 * two different resellers — and a reseller and the platform — can each have an "about"
 * page. Platform-page slug uniqueness (reseller_id IS NULL) is enforced at the
 * application layer instead, since MySQL treats NULLs as distinct in a unique index;
 * the admin PageController has always validated slugs with `unique:pages,slug`, now
 * scoped to platform rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('reseller_id')
                ->nullable()
                ->after('id')
                ->constrained('resellers')
                ->cascadeOnDelete();
        });

        Schema::table('pages', function (Blueprint $table) {
            // Drop the global slug unique so per-tenant slugs can repeat across tenants.
            $table->dropUnique('pages_slug_unique');
            $table->unique(['reseller_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['reseller_id', 'slug']);
            $table->dropConstrainedForeignId('reseller_id');
            $table->unique('slug');
        });
    }
};
