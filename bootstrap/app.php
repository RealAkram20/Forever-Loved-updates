<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Str;

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
        $middleware->trustProxies(at: '*');

        $middleware->prependToGroup('web', \App\Http\Middleware\InstallMiddleware::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\AddRequestLogContext::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->validateCsrfTokens(except: [
            'payment/ipn',
            'install/execute/*',
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Attach a redacted snapshot of the request to every reported exception.
        $exceptions->context(function (Throwable $e) {
            $request = request();
            if (! $request instanceof \Illuminate\Http\Request) {
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
