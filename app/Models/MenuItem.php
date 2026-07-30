<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'url',
        'route_name',
        'route_parameters',
        'open_in_new_tab',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'route_parameters' => 'array',
            'open_in_new_tab' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function resolvedUrl(): string
    {
        if (is_string($this->url) && $this->url !== '') {
            if (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://') || str_starts_with($this->url, '/')) {
                return $this->url;
            }

            return url($this->url);
        }

        $reseller = $this->menu?->reseller;

        if ($this->route_name === 'cms.page') {
            $slug = is_array($this->route_parameters) ? ($this->route_parameters['slug'] ?? null) : null;
            if (! is_string($slug) || $slug === '') {
                return '#';
            }

            // A reseller's page lives at *their* address. url() resolves against the current
            // request root, which is right on their own host but wrong for the /r/{slug} path
            // fallback that subdirectory and development installs use — there it would point
            // at the platform's /{slug} instead of theirs.
            if (! $reseller) {
                return url('/'.$slug);
            }

            return $slug === Page::SLUG_VISITOR_HOME
                ? $reseller->publicBaseUrl()
                : $reseller->publicUrlForSlug($slug);
        }

        if (is_string($this->route_name) && $this->route_name !== '' && Route::has($this->route_name)) {
            // Relative for a reseller's menu. AppServiceProvider calls
            // URL::forceRootUrl(config('app.url')) so subdirectory installs work, which pins
            // every absolute generated URL to the *platform's* address — so route() here would
            // put a link to our site in their navigation. A path resolves against whatever
            // host the visitor is already on, which is theirs.
            return $reseller
                ? route($this->route_name, $this->route_parameters ?? [], false)
                : route($this->route_name, $this->route_parameters ?? []);
        }

        return '#';
    }

    /**
     * Value used in the admin route &lt;select&gt; (matches MenuController option keys).
     */
    public function routeSelectValue(): string
    {
        if (! is_string($this->route_name) || $this->route_name === '') {
            return '';
        }
        if ($this->route_name === 'cms.page') {
            $slug = is_array($this->route_parameters) ? ($this->route_parameters['slug'] ?? null) : null;
            if (is_string($slug) && $slug !== '') {
                return 'cms.page::'.$slug;
            }

            return '';
        }

        return $this->route_name;
    }

    public function isActive(?string $currentRouteName): bool
    {
        if (! $currentRouteName || ! is_string($this->route_name) || $this->route_name === '') {
            return false;
        }

        if ($this->route_name !== $currentRouteName) {
            return false;
        }

        if ($this->route_name === 'cms.page') {
            $slug = is_array($this->route_parameters) ? ($this->route_parameters['slug'] ?? null) : null;

            return is_string($slug) && $slug !== '' && request()->route('slug') === $slug;
        }

        return true;
    }
}
