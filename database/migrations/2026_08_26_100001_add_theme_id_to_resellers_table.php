<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which theme this reseller's public site renders with.
 *
 * Nullable, and null is the meaningful default: every existing reseller keeps rendering
 * exactly what they render today, because null resolves to the base template — which is
 * where the current design now lives. Nobody's site moves because a theme engine shipped.
 *
 * nullOnDelete rather than cascade: deleting a theme must unstyle a site, never delete the
 * business that was using it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->foreignId('theme_id')->nullable()->after('primary_color')
                ->constrained('themes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropForeign(['theme_id']);
            $table->dropColumn('theme_id');
        });
    }
};
