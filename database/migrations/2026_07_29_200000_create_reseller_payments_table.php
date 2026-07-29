<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a reseller has actually paid the platform. Distinct from payment_orders, which
 * records their *clients* paying *them* — money flowing the other direction entirely.
 *
 * Deliberately gateway-agnostic: an annual B2B contract is usually settled by transfer or
 * invoice, so `method` records how, and a gateway reference is optional. Adding Pesapal
 * capture later means writing rows with method='pesapal', not reshaping this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            // Null until the first payment: a reseller can be created and set up long
            // before their contract starts, and inventing a start date would make them
            // look overdue for a period nobody agreed to.
            $table->date('billing_period_start')->nullable()->after('status');
            $table->date('billing_period_end')->nullable()->after('billing_period_start');
        });

        Schema::create('reseller_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('USD');

            // The period this payment bought, snapshotted so history stays readable after
            // a tier's price or allowance changes.
            $table->date('period_start');
            $table->date('period_end');
            $table->string('tier_name')->nullable();
            $table->decimal('tier_annual_price', 12, 2)->nullable();
            $table->unsignedInteger('overage_profiles')->default(0);
            $table->decimal('overage_amount', 12, 2)->default(0);

            $table->string('method')->default('manual');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('paid_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reseller_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_payments');

        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn(['billing_period_start', 'billing_period_end']);
        });
    }
};
