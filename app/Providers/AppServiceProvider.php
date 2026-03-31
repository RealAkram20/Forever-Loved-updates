<?php

namespace App\Providers;

use App\Models\Menu;
use App\Services\SeoMetaResolver;
use App\SiteBlocks\SiteBlockRegistry;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SiteBlockRegistry::class, fn () => new SiteBlockRegistry);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force APP_URL for all generated URLs (fixes subdirectory: /Forever-love)
        $appUrl = config('app.url');
        if ($appUrl) {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }

        View::composer('layouts.fullscreen-layout', function ($view) {
            app(SeoMetaResolver::class)->applyToFullscreenLayout($view);
        });

        View::composer(['components.home-header', 'components.visitor-footer'], function ($view) {
            $view->with([
                'headerNavItems' => Menu::navigationFor(Menu::LOCATION_HEADER),
                'footerQuickItems' => Menu::navigationFor(Menu::LOCATION_FOOTER_QUICK),
                'footerCompanyItems' => Menu::navigationFor(Menu::LOCATION_FOOTER_COMPANY),
                'footerQuickMenu' => Menu::query()->where('location', Menu::LOCATION_FOOTER_QUICK)->first(),
                'footerCompanyMenu' => Menu::query()->where('location', Menu::LOCATION_FOOTER_COMPANY)->first(),
            ]);
        });
    }
}
