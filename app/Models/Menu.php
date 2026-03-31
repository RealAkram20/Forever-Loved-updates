<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Menu extends Model
{
    public const LOCATION_HEADER = 'header';

    public const LOCATION_FOOTER_QUICK = 'footer_quick';

    public const LOCATION_FOOTER_COMPANY = 'footer_company';

    protected $fillable = [
        'location',
        'label',
    ];

    public function rootMenuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function allItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /**
     * Root-level items for a location, ordered (empty collection if no menu).
     */
    public static function navigationFor(string $location): Collection
    {
        $menu = static::query()->where('location', $location)->first();
        if (! $menu) {
            return collect();
        }

        return $menu->rootMenuItems()->get();
    }
}
