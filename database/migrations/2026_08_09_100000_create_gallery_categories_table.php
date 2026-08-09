<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms in the gallery.
 *
 * The gallery had exactly one axis of organisation — photo or video — so a memorial with
 * two hundred pictures was one undifferentiated grid, and a visitor who came to see the
 * school years had to scroll all of it. A category is the family's own division of that
 * grid: "School Life", "The Farm", whatever the life actually had in it.
 *
 * Per memorial rather than platform-wide, because the vocabulary that fits one life does
 * not fit the next, and a global list would either be too vague to sort by or too specific
 * to apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memorial_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            // A stable key for the filter chips, so the markup doesn't have to carry the
            // display name around and a rename doesn't reshuffle the DOM.
            $table->string('slug', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['memorial_id', 'slug']);
            $table->index(['memorial_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_categories');
    }
};
