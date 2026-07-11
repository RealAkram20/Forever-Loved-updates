<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decorates every log line written during the request (not just exceptions)
 * with who/where context, so production logs are traceable per request.
 */
class AddRequestLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::withContext(array_filter([
            'request_id' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'route' => $request->route()?->getName(),
            'url' => $request->method().' '.$request->path(),
            'memorial' => $this->memorialSlug($request),
            'ip' => $request->ip(),
        ]));

        return $next($request);
    }

    private function memorialSlug(Request $request): ?string
    {
        $memorial = $request->route('memorial');
        if (is_object($memorial)) {
            return $memorial->slug ?? (string) ($memorial->id ?? null);
        }

        return $memorial ?? $request->route('slug');
    }
}
