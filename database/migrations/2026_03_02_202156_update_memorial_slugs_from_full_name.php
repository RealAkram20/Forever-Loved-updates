<?php

use App\Models\Memorial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update existing memorial slugs to use full_name format (e.g. miiro-rio-akram).
     */
    public function up(): void
    {
        Memorial::all()->each(function (Memorial $memorial) {
            $baseSlug = Str::slug($memorial->full_name);
            $slug = $baseSlug;
            $suffix = 0;

            while (
                Memorial::where('slug', $slug)
                    ->where('id', '!=', $memorial->id)
                    ->exists()
            ) {
                $suffix++;
                $slug = $baseSlug . '-' . $suffix;
            }

            $memorial->update(['slug' => $slug]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably restore old slugs
    }
};
