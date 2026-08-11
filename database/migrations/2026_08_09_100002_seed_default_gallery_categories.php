<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every memorial that already exists the same three rooms a new one is born with.
 *
 * Childhood, Family & Friends, Milestones. Chosen to hold for an infant and for a
 * ninety-year-old, for any gender, and for a life that took no particular shape: nothing
 * here presumes a school, a career, a marriage or a faith. "Milestones" is the deliberate
 * catch-all for the graduations, weddings, enlistments and awards that would otherwise each
 * need naming, and naming them would make the list wrong for whoever had none of them.
 *
 * Written against the query builder rather than the models: a migration has to keep working
 * after GalleryCategory's fillable or hooks change under it.
 *
 * Idempotent — a memorial that already has any category is skipped, so this can be re-run
 * and so a memorial created between the table migration and this one isn't given six.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        ['name' => 'Childhood', 'slug' => 'childhood'],
        ['name' => 'Family & Friends', 'slug' => 'family-friends'],
        ['name' => 'Milestones', 'slug' => 'milestones'],
    ];

    public function up(): void
    {
        $now = now();

        DB::table('memorials')
            ->select('id')
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('gallery_categories')
                ->whereColumn('gallery_categories.memorial_id', 'memorials.id'))
            ->orderBy('id')
            ->chunk(500, function ($memorials) use ($now) {
                $rows = [];

                foreach ($memorials as $memorial) {
                    foreach (self::DEFAULTS as $index => $category) {
                        $rows[] = [
                            'memorial_id' => $memorial->id,
                            'name' => $category['name'],
                            'slug' => $category['slug'],
                            'sort_order' => $index,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('gallery_categories')->insert($rows);
            });
    }

    /**
     * Only the untouched defaults are withdrawn, and only where they hold nothing. A family
     * that filed photos into "Childhood" has made it theirs; rolling back a migration is not
     * a reason to un-file them.
     */
    public function down(): void
    {
        DB::table('gallery_categories')
            ->whereIn('slug', array_column(self::DEFAULTS, 'slug'))
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('media')
                ->whereColumn('media.gallery_category_id', 'gallery_categories.id'))
            ->delete();
    }
};
