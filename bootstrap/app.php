<?php

use App\Http\Middleware\AddRequestLogContext;
use App\Http\Middleware\InstallMiddleware;
use App\Http\Middleware\AnnounceThemePreview;
use App\Http\Middleware\ResolveResellerByHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Customers deploy behind TLS-terminating proxies/CDNs (Hostinger,
        // Cloudflare). Without this, HTTPS requests look like HTTP: secure
        // session cookies are never sent back (breaking OAuth state checks)
        // and generated URLs use the wrong scheme.
        //
        // This was `at: '*'` until 2026-09-02. That trusted the client's own
        // X-Forwarded-For, so Request::ip() returned whatever the caller sent —
        // and that is the key every `throttle` middleware on this app's public
        // routes uses. See App\Support\TrustedProxies; TRUSTED_PROXIES=* undoes it.
        $middleware->trustProxies(at: \App\Support\TrustedProxies::list());

        // Catches a tripped honeypot on any web POST, before the controller runs. On the
        // group rather than per route: a form is protected the moment it includes
        // `partials.honeypot`, and a form that never renders the field is untouched,
        // because only a *filled* value counts as a trip.
        $middleware->appendToGroup('web', \App\Http\Middleware\HoneypotGuard::class);

        $middleware->prependToGroup('web', InstallMiddleware::class);

        // Widening the session cookie's domain left every browser holding two cookies of the
        // same name — the old host-only one and the new dotted one — and PHP keeps the first,
        // which browsers order oldest-first. Every request landed on a fresh session and every
        // write answered "CSRF token mismatch". Prepended so it runs last on the way out, after
        // EncryptCookies. Removable one session lifetime after that deploy.
        $middleware->prependToGroup('web', \App\Http\Middleware\ForgetStaleHostOnlyCookies::class);
        $middleware->appendToGroup('web', AddRequestLogContext::class);

        // Binds each session to the password it was created under, which is what makes
        // Auth::logoutOtherDevices() (ProfileController::updatePassword) actually end the
        // other sessions. Without it that call rehashes and returns, the attacker's session
        // stays live, and changing your password after a compromise achieves nothing.
        $middleware->appendToGroup('web', AuthenticateSession::class);

        // Every page on a reseller's host belongs to that reseller. Only two routes declare a
        // domain, so without this the login screen their clients use, and our own pricing page,
        // were served unbranded on their white-labeled domain. Short-circuits on our own host
        // before any query, so the platform pays nothing for it.
        $middleware->appendToGroup('web', ResolveResellerByHost::class);

        // Runs on the way out, so it sees the tenant however it was bound — including the
        // /r/{slug} fallback, where the binding happens in a route middleware inside $next().
        // Marks previewed pages no-store and welds the "this is a preview" bar onto them.
        $middleware->appendToGroup('web', AnnounceThemePreview::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->validateCsrfTokens(except: [
            'payment/ipn',
            'install/execute/*',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Attach a redacted snapshot of the request to every reported exception.
        $exceptions->context(function (Throwable $e) {
            $request = request();
            if (! $request instanceof Request) {
                return [];
            }

            $denylist = ['password', 'password_confirmation', 'current_password', 'token', '_token', 'secret', 'consumer_key', 'consumer_secret', 'api_key', 'card', 'card_number', 'cvv'];
            $payload = collect($request->except($denylist))
                ->map(fn ($v) => is_string($v) ? Str::limit($v, 200) : (is_scalar($v) || is_null($v) ? $v : '['.gettype($v).']'))
                ->take(30)
                ->all();

            return array_filter([
                'payload' => $payload ?: null,
            ]);
        });
    })->create();
