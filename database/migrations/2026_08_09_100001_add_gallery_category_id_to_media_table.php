<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which room a picture hangs in.
 *
 * Nullable throughout: every photo already on the platform is uncategorised, and filing is
 * something a family does when they feel like it, not a precondition for uploading.
 *
 * nullOnDelete is load-bearing. Deleting a category must un-file its photos, never destroy
 * them — a curator tidying up their category list is not asking to lose the pictures in it,
 * and there is no undo on a deleted file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('gallery_category_id')
                ->nullable()
                ->after('type')
                ->constrained('gallery_categories')
                ->nullOnDelete();

            $table->index(['memorial_id', 'gallery_category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['memorial_id', 'gallery_category_id']);
            $table->dropConstrainedForeignId('gallery_category_id');
        });
    }
};
