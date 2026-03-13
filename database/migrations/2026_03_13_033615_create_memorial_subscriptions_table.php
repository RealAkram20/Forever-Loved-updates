<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorial_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->boolean('notify_life_chapters')->default(true);
            $table->boolean('notify_tributes')->default(true);
            $table->timestamps();

            $table->unique(['memorial_id', 'user_id'], 'mem_sub_user_unique');
            $table->unique(['memorial_id', 'guest_email'], 'mem_sub_guest_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memorial_subscriptions');
    }
};
