<?php

namespace App\Http\Middleware;

use App\Support\Honeypot;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catches a tripped honeypot anywhere on the site, before the controller runs.
 *
 * On the `web` group rather than named on individual routes, because the alternative is a
 * check per controller and the thing this codebase already proved is that a protection written
 * down in more than one place stops being written down in one of them. A form is protected the
 * moment it includes `partials.honeypot`; nothing has to be registered.
 *
 * **A form without the field is unaffected.** `Honeypot::tripped()` only fires on a *filled*
 * value, so every existing form — admin screens, the editor, anything that never renders the
 * partial — passes through untouched. That is what makes this safe to put on the whole group.
 *
 * **Before validation, deliberately.** A caught bot gets a success-shaped answer and the
 * controller never runs. Letting it validate first would return a 422 listing every field name
 * it got wrong, which is free reconnaissance and a failure worth retrying against.
 */
class HoneypotGuard
{
    /**
     * What a caught submission is told, per route.
     *
     * The point is that the answer is indistinguishable from the real one, so where a form has
     * a distinctive success message it is repeated here. Anything not listed gets the neutral
     * line below, which is still a success and still tells a bot nothing.
     *
     * Kept in one map rather than pushed back into the controllers: a controller that has to
     * remember to answer spam correctly is a controller that will forget.
     */
    private const MESSAGES = [
        'contact.send' => "Your message has been sent. We'll get back to you soon.",
    ];

    private const DEFAULT_MESSAGE = 'Thank you. Your submission has been received.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! Honeypot::tripped($request)) {
            return $next($request);
        }

        Honeypot::log($request, $request->route()?->getName() ?? $request->path());

        // An XHR caller gets a plain 200 with nothing in it. Returning a redirect to a fetch()
        // would surface in the page's own error handling as something odd, and a 4xx would tell
        // the caller it had been spotted.
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with(
            'success',
            self::MESSAGES[$request->route()?->getName()] ?? self::DEFAULT_MESSAGE
        );
    }
}
