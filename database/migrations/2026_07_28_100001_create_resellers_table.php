<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reseller_tier_id')->nullable()->constrained('reseller_tiers')->nullOnDelete();
            $table->string('status')->default('active'); // active, suspended
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->nullable();
            $table->boolean('pesapal_enabled')->default(false);
            $table->string('pesapal_consumer_key')->nullable();
            $table->text('pesapal_consumer_secret')->nullable();
            $table->string('pesapal_environment')->default('sandbox');
            $table->string('pesapal_ipn_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resellers');
    }
};
