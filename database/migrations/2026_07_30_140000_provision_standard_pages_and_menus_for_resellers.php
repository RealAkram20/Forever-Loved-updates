<?php

use App\Models\Reseller;
use App\Support\ResellerSiteProvisioner;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfills every existing reseller with the standard pages and default navigation that new
 * ones now get on creation (Reseller::booted).
 *
 * Without this the change would only reach tenants created from today, leaving the resellers
 * already trading with the empty header and the missing About page that prompted it.
 *
 * Additive and idempotent, so it is safe to re-run and safe alongside the creation hook:
 * pages and menu locations that already exist are left exactly as they are, including ones a
 * reseller has deliberately switched off or emptied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Reseller::query()->chunkById(50, function ($resellers) {
            foreach ($resellers as $reseller) {
                // appendMissingLinks: a menu built before standard pages existed cannot have
                // had these links deliberately removed, so filling the gap is safe here in a
                // way it would not be on a later run.
                ResellerSiteProvisioner::provision($reseller, appendMissingLinks: true);
            }
        });
    }

    public function down(): void
    {
        // Deliberately irreversible. The rows this creates are indistinguishable from ones a
        // reseller has since edited — their own About copy, their own reordered header — and
        // deleting on rollback would destroy that work to undo a default.
    }
};
