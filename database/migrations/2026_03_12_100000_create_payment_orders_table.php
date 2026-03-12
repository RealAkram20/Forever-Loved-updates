<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_reference', 50)->unique();
            $table->string('order_tracking_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10);
            $table->string('status')->default('pending'); // pending, completed, failed, cancelled
            $table->string('payment_gateway')->default('pesapal');
            $table->string('payment_method')->nullable(); // MTN, Airtel, Card, etc.
            $table->string('confirmation_code')->nullable();
            $table->json('metadata')->nullable(); // e.g. memorial signup context
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('merchant_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
