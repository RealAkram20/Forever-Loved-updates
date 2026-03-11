<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorial_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_hash', 64)->index();
            $table->string('share_type', 20)->index();
            $table->timestamp('shared_at');
            $table->index(['memorial_id', 'shared_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memorial_shares');
    }
};
