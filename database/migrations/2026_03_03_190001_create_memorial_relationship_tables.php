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
        Schema::create('memorial_notable_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('memorial_co_founders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('memorial_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('child_name');
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->timestamps();
        });

        Schema::create('memorial_spouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('spouse_name');
            $table->unsignedSmallInteger('marriage_start_year')->nullable();
            $table->unsignedSmallInteger('marriage_end_year')->nullable();
            $table->timestamps();
        });

        Schema::create('memorial_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('parent_name');
            $table->string('relationship_type')->default('biological'); // biological, adoptive
            $table->timestamps();
        });

        Schema::create('memorial_siblings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('sibling_name');
            $table->timestamps();
        });

        Schema::create('memorial_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('institution_name');
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->string('degree')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memorial_education');
        Schema::dropIfExists('memorial_siblings');
        Schema::dropIfExists('memorial_parents');
        Schema::dropIfExists('memorial_spouses');
        Schema::dropIfExists('memorial_children');
        Schema::dropIfExists('memorial_co_founders');
        Schema::dropIfExists('memorial_notable_companies');
    }
};
