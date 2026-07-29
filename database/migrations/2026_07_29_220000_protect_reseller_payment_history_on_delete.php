<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reseller_payments.reseller_id was the only CASCADE among the reseller foreign keys, so
 * deleting a reseller destroyed every record of money they had paid the platform — with no
 * soft delete and no archive.
 *
 * The asymmetry gave it away: payment_orders (their clients paying them) is SET NULL and
 * survives, while reseller_payments did not. Financial history should outlive the business
 * relationship that produced it, so this restricts instead: a reseller with payments on
 * record cannot be deleted at all. There is no delete route for resellers, and the intended
 * end-of-partnership path is rollover(), which keeps everything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_payments', function (Blueprint $table) {
            $table->dropForeign(['reseller_id']);
            $table->foreign('reseller_id')->references('id')->on('resellers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reseller_payments', function (Blueprint $table) {
            $table->dropForeign(['reseller_id']);
            $table->foreign('reseller_id')->references('id')->on('resellers')->cascadeOnDelete();
        });
    }
};
