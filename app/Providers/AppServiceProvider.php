<?php

namespace App\Providers;

use App\Helpers\ThemeSetting;
use App\Listeners\RecordLastLogin;
use App\Models\Menu;
use App\Services\SeoMetaResolver;
use App\SiteBlocks\SiteBlockRegistry;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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

        // Registered explicitly rather than relying on event discovery, which is off by
        // default once the app is cached — a listener that silently stops running in
        // production is worse than no listener at all.
        Event::listen(Login::class, RecordLastLogin::class);

        View::composer('layouts.fullscreen-layout', function ($view) {
            app(SeoMetaResolver::class)->applyToFullscreenLayout($view);
        });

        View::composer(['components.home-header', 'components.visitor-footer'], function ($view) {
            // Whose menus these are is decided by whose site is being served.
            //
            // The platform's menus (reseller_id NULL, built in Admin → Menus) point at our
            // About, Pricing, Contact and directory, and must never appear on a reseller's
            // white-labeled domain — an admin-defined item is opaque here, so there is no way
            // to tell "Pricing" apart from a link the reseller would legitimately want. That
            // used to mean serving nothing at all on a reseller site, which left them a
            // one-item nav they had no way to change. Now they get their own set, built in
            // Reseller → Menus, and fall through to the blades' tenant-aware defaults only
            // while they have not defined one.
            //
            // Resolved here rather than in each blade so a fourth menu location cannot be
            // added later and quietly leak.
            $tenantId = ThemeSetting::isResellerSite() ? ThemeSetting::tenant()?->id : null;

            $view->with([
                'headerNavItems' => Menu::navigationFor(Menu::LOCATION_HEADER, $tenantId),
                'footerQuickItems' => Menu::navigationFor(Menu::LOCATION_FOOTER_QUICK, $tenantId),
                'footerCompanyItems' => Menu::navigationFor(Menu::LOCATION_FOOTER_COMPANY, $tenantId),
                'footerQuickMenu' => Menu::forLocation(Menu::LOCATION_FOOTER_QUICK, $tenantId),
                'footerCompanyMenu' => Menu::forLocation(Menu::LOCATION_FOOTER_COMPANY, $tenantId),
            ]);
        });
    }
}
