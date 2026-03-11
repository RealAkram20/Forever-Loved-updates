<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorial_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_hash', 64)->index();
            $table->timestamp('viewed_at');
            $table->index(['memorial_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memorial_views');
    }
};
