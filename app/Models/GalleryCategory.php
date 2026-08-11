<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GalleryCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'memorial_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * What every memorial starts with. Neutral as to age, gender, faith and era — see the
     * seed migration for why these three and not a longer list.
     */
    public const DEFAULTS = ['Childhood', 'Family & Friends', 'Milestones'];

    /**
     * More than this and the filter row stops being a way to find a photo and becomes
     * another thing to scroll. Enforced in the controller, stated here so the number lives
     * next to what it limits.
     */
    public const MAX_PER_MEMORIAL = 20;

    /**
     * Keys the gallery uses for the two groups that have no row of their own: media the
     * memorial's own stories carry, and media nobody has filed yet.
     */
    public const KEY_STORIES = 'stories';
    public const KEY_UNFILED = 'uncategorised';

    /**
     * The slug is assigned once and then left alone. It keys the filter chips and the
     * markup, so regenerating it on rename would mean "School Life" → "School Years"
     * silently orphaned every element referring to the old one, for no gain — nothing
     * resolves a category by slug from outside the page.
     */
    protected static function booted(): void
    {
        static::creating(function (self $category) {
            if (! $category->slug) {
                $category->slug = self::uniqueSlug($category->memorial_id, $category->name);
            }
        });
    }

    private static function uniqueSlug(int $memorialId, string $name): string
    {
        // A name of only punctuation or non-Latin script slugs to an empty string, which
        // would collide with every other such name on the memorial.
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (self::where('memorial_id', $memorialId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
