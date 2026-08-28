<?php

namespace App\Providers;

use App\Helpers\ThemeSetting;
use App\Listeners\RecordLastLogin;
use App\Models\Menu;
use App\Services\SeoMetaResolver;
use App\SiteBlocks\SiteBlockRegistry;
use App\Themes\ActiveTheme;
use App\Themes\ThemeRegistry;
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
        $this->app->singleton(ThemeRegistry::class, fn () => new ThemeRegistry);
        $this->app->singleton(ActiveTheme::class, fn ($app) => new ActiveTheme($app->make(ThemeRegistry::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The base template is `resources/views` itself. The platform's own site lives there
        // and nothing is moved out of it — an earlier version of this relocated the visitor
        // blades into `themes/basic/`, which meant the main website was being served from
        // inside the reseller theme system. That is the wrong dependency: the platform should
        // not render through machinery that exists for tenants.
        //
        // `themes/basic/` now carries only a manifest, so the catalogue has a row to point at.
        // It ships no blades and needs no view location.
        //
        // Templates that *do* ship blades are prepended per request by ActiveTheme, so the
        // chain is [chosen template] → resources/views. The platform's host prepends nothing
        // at all, which is what keeps the main site exactly as it was.

        // Force APP_URL for all generated URLs (fixes subdirectory: /Forever-love)
        $appUrl = config('app.url');
        if ($appUrl) {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }

        // Registered explicitly rather than relying on event discovery, which is off by
        // default once the app is cached — a listener that silently stops running in
        // production is worse than no listener at all.
        Event::listen(Login::class, RecordLastLogin::class);

        // The stock reset email is signed by config('app.name') top to bottom. A
        // reseller's client must get one that reads as the site they use — name in
        // the subject, the sender line and the sign-off, and a link built relative so
        // it roots at whichever host serves the request.
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $siteName = \App\Helpers\BrandingHelper::displayNameFor($notifiable->reseller);
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $mail = (new \Illuminate\Notifications\Messages\MailMessage)
                ->from(config('mail.from.address'), $siteName)
                ->subject("Reset Password - {$siteName}")
                ->line('You are receiving this email because we received a password reset request for your account.')
                ->action('Reset Password', $url)
                ->line('If you did not request a password reset, no further action is required.')
                ->salutation(new \Illuminate\Support\HtmlString('Regards,<br>'.e($siteName)));
            if ($notifiable->reseller?->contact_email) {
                $mail->replyTo($notifiable->reseller->contact_email);
            }

            return $mail;
        });

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
            $tenantId = ThemeSetting::siteTenantId();

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
