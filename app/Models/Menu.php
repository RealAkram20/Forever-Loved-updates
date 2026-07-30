<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Menu extends Model
{
    public const LOCATION_HEADER = 'header';

    public const LOCATION_FOOTER_QUICK = 'footer_quick';

    public const LOCATION_FOOTER_COMPANY = 'footer_company';

    protected $fillable = [
        'reseller_id',
        'location',
        'label',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function rootMenuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function allItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /**
     * Constrains a query to one tenant's menus — or, for null, to the platform's own.
     *
     * A plain `where('reseller_id', $id)` would silently match nothing for null instead of
     * matching the platform rows, which is the difference between "our menu" and "no menu".
     */
    public function scopeForTenant(Builder $query, ?int $resellerId): Builder
    {
        return $resellerId === null
            ? $query->whereNull('reseller_id')
            : $query->where('reseller_id', $resellerId);
    }

    public static function forLocation(string $location, ?int $resellerId = null): ?self
    {
        return static::query()->forTenant($resellerId)->where('location', $location)->first();
    }

    /**
     * Root-level items for a location, ordered (empty collection if no menu).
     *
     * Deliberately never falls back to the platform's menu when a reseller has not built
     * one. Those items are our About, Pricing and Contact; serving them on a reseller's
     * white-labeled domain is the leak this scoping exists to prevent. An empty collection
     * lets the header and footer blades use their own tenant-aware defaults instead.
     */
    public static function navigationFor(string $location, ?int $resellerId = null): Collection
    {
        return static::forLocation($location, $resellerId)?->rootMenuItems()->get() ?? collect();
    }
}
