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
            // The header and footer menus are the *platform's* own, built in Admin → Menus, and
            // they point at our About, Pricing, Contact and directory. On a reseller's own site
            // they are withheld: the blades' built-in fallbacks are tenant-aware and drop those
            // destinations, whereas an admin-defined item is opaque here — there is no way to
            // tell "Pricing" apart from a link the reseller would legitimately want.
            //
            // Done here rather than in each blade so a fourth menu location cannot be added
            // later and quietly leak.
            $resellerSite = ThemeSetting::isResellerSite();

            $view->with([
                'headerNavItems' => $resellerSite ? collect() : Menu::navigationFor(Menu::LOCATION_HEADER),
                'footerQuickItems' => $resellerSite ? collect() : Menu::navigationFor(Menu::LOCATION_FOOTER_QUICK),
                'footerCompanyItems' => $resellerSite ? collect() : Menu::navigationFor(Menu::LOCATION_FOOTER_COMPANY),
                'footerQuickMenu' => $resellerSite ? null : Menu::query()->where('location', Menu::LOCATION_FOOTER_QUICK)->first(),
                'footerCompanyMenu' => $resellerSite ? null : Menu::query()->where('location', Menu::LOCATION_FOOTER_COMPANY)->first(),
            ]);
        });
    }
}
