<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied only to the /widget/{slug} embed route so it can be framed on a reseller's
 * own external site. Phase 1 has no per-reseller allowed-origins list yet, so this is
 * deliberately wide open — every other route in the app is unaffected since this
 * middleware is never attached globally.
 */
class EmbedFrameHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');

        return $response;
    }
}
