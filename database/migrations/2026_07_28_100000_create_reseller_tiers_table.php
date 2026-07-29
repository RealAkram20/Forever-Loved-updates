<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('sort_order')->default(0);
            $table->decimal('annual_price', 10, 2)->default(0);
            $table->unsignedInteger('memorial_profile_allowance')->nullable();
            $table->decimal('price_per_additional_profile', 10, 2)->default(0);
            $table->unsignedInteger('storage_limit_gb')->nullable();
            $table->boolean('feature_embedding')->default(false);
            $table->boolean('feature_domain_routing')->default(false);
            $table->boolean('feature_business_analytics')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_tiers');
    }
};
