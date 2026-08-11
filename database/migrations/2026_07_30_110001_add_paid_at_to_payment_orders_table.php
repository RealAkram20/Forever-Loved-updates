<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When an order was actually paid.
 *
 * Revenue-by-date previously had to be inferred from `updated_at` on completed rows, which
 * is wrong the moment anything else touches the row after payment — a refund note, a
 * metadata backfill, a status correction. Reports that reconcile against a payment
 * gateway's own statement cannot be built on a column that means "last written".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
        });

        // Backfill from updated_at so historical revenue reports are not simply empty.
        // These values are APPROXIMATE — they are the last-write time, not a confirmed
        // settlement time. Orders completed from here on carry a real timestamp written by
        // PaymentResultProcessor. Anything reconciling to the cent against a gateway
        // statement should treat rows created before this migration with suspicion.
        DB::table('payment_orders')
            ->where('status', 'completed')
            ->whereNull('paid_at')
            ->update(['paid_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
