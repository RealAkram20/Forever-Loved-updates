<?php

use App\Themes\ThemeCatalogue;
use Illuminate\Database\Migrations\Migration;

/**
 * Makes the shipped templates selectable on deploy.
 *
 * A theme that requires somebody to remember `themes:sync` before anyone can choose it is a
 * theme that sits invisible until the first support ticket. Same reasoning as
 * 2026_07_30_140000, which provisions standard pages and menus rather than leaving every
 * reseller a switch to find.
 *
 * Idempotent, so re-running a migration set or adding a template later costs nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        ThemeCatalogue::sync();
    }

    public function down(): void
    {
        // Deliberately not deleting the catalogue. resellers.theme_id is nullOnDelete, so
        // dropping these rows would silently move every themed site back to the base design —
        // a much larger consequence than the migration being rolled back implies. The
        // create_themes_table rollback removes them along with the table.
    }
};
