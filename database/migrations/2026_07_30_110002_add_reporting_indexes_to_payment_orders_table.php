<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the revenue reports group by.
 *
 * payment_orders carried only (user_id, status) and a merchant_reference index — both
 * useless to a report that asks "everything this reseller sold last quarter". Without
 * these, every revenue report is a full table scan on the one table that grows fastest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->index(['reseller_id', 'status'], 'payment_orders_reseller_status_index');
            // Date-windowed queries run against paid_at where it is set and created_at
            // otherwise (pending and failed orders never get a paid_at), so both are indexed.
            $table->index('created_at', 'payment_orders_created_at_index');
            $table->index('paid_at', 'payment_orders_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropIndex('payment_orders_reseller_status_index');
            $table->dropIndex('payment_orders_created_at_index');
            $table->dropIndex('payment_orders_paid_at_index');
        });
    }
};
