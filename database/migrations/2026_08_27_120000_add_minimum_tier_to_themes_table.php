<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lowest reseller tier that may *apply* this theme.
 *
 * Null means ungated, and null is the default for every existing row — so nothing changes
 * for anybody until an admin deliberately gates a theme. Same principle as
 * add_theme_id_to_resellers: shipping a mechanism must not move a single live site.
 *
 * Gating is on the **apply** action alone. A reseller who already runs a theme keeps it if
 * their tier later drops, exactly as unpublishing a theme leaves the sites already using it
 * where they are. The alternative — a site changing design because a subscription lapsed — is
 * not something any funeral home would forgive, and they would find out from a family.
 *
 * A tier reference rather than a copied sort_order: the business means "Dignified is a
 * Professional feature", and it should keep meaning that if the tiers are ever reordered or
 * renamed. The comparison itself is by sort_order, which is what makes "minimum" an order
 * rather than an equality.
 *
 * nullOnDelete: deleting a tier ungates the themes that pointed at it. The gentler of the two
 * failures — losing a restriction is recoverable in the admin screen, while silently locking
 * every reseller out of a theme is discovered by support ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->foreignId('minimum_tier_id')->nullable()->after('is_published')
                ->constrained('reseller_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropForeign(['minimum_tier_id']);
            $table->dropColumn('minimum_tier_id');
        });
    }
};
