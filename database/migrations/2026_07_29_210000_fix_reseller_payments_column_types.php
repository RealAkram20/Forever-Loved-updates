<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two corrections to reseller_payments, both MySQL-only and both invisible to the SQLite
 * test suite. Applied as a follow-up rather than by editing the original migration,
 * because that one has already run.
 *
 * 1. paid_at was declared timestamp() NOT NULL with no default. With
 *    explicit_defaults_for_timestamp=0 — the setting on the production server — MySQL
 *    applies its legacy rule to the first NOT NULL TIMESTAMP in a table and silently
 *    attaches `DEFAULT current_timestamp() ON UPDATE current_timestamp()`. Any update to
 *    a payment row, even one only touching `notes`, would rewrite the recorded payment
 *    date to "now" and reorder billing history, unrecoverably. dateTime() carries no such
 *    behaviour.
 *
 * 2. currency was varchar(8), but the value written comes from the payments.currency
 *    setting, which admin validates at max:10. A 9- or 10-character currency would throw
 *    "Data too long" in strict mode and the payment would never be recorded. Widened to
 *    match payment_orders.currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_payments', function (Blueprint $table) {
            $table->dateTime('paid_at')->change();
            $table->string('currency', 10)->default('USD')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reseller_payments', function (Blueprint $table) {
            $table->timestamp('paid_at')->change();
            $table->string('currency', 8)->default('USD')->change();
        });
    }
};
