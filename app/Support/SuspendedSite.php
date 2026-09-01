<?php

namespace App\Support;

use App\Models\Memorial;
use App\Models\Reseller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a suspended reseller's addresses still answer.
 *
 * Suspension is a sanction against a *business* — an unpaid invoice, a dispute, an account
 * being wound down. The families whose memorials that business happens to host have done
 * nothing, and a memorial is not leverage: it is where somebody's children go to read what was
 * written about their father. So the front of the shop closes and the memorials stay up.
 *
 * That split already half-existed. EnsureResellerActive has always shut the reseller's own
 * staff dashboard, and ResolveReseller's docblock has always said, in as many words, that it
 * deliberately does not 404 a suspended tenant because the memorial pages must keep working.
 * What was missing is the other half: nothing closed the *site*. A suspended funeral home kept
 * a fully browsable homepage, About, Pricing, Contact and memorial directory, and kept taking
 * new memorial signups.
 *
 * Closed by default, open only to what is named below. A block-list would have to be
 * remembered every time a page is added, and the page somebody forgets is a page a suspended
 * business is still trading from.
 */
class SuspendedSite
{
    /**
     * Open on a suspended site whatever the URL says.
     *
     * Signing in stays open because the person who owns a memorial is usually a family member,
     * not the funeral home, and locking them out of their own page would make them collateral
     * in somebody else's billing dispute. The dashboard is here for the same reason; the
     * reseller's *own* staff are already stopped there by EnsureResellerActive, which is the
     * guard that should be doing it. Unsubscribing stays open because asking someone to wait
     * for a business to pay its bill before they may stop receiving its email is indefensible.
     */
    private const ALWAYS_OPEN = [
        'memorial.unsubscribe',
        'login',
        'logout',
        'password.',
        'verification.',
        'reseller.login',
        'dashboard',
    ];

    /**
     * Open only when the slug in the URL really is one of this reseller's memorials.
     *
     * The route name alone is not enough, and this is the trap that made a first version of
     * this wrong. `memorial.public.reseller` and `memorial.public.reseller-path` are a single
     * route serving two different things: PublicMemorialController::showForReseller looks for
     * a memorial with that slug and, failing to find one, renders the reseller's CMS page of
     * the same name. So `/services` arrives under a route called `memorial.public.*` and is a
     * marketing page. Allowing the route wholesale left a suspended funeral home's Services
     * page serving 200 — found by opening it, not by the tests.
     *
     * One indexed lookup on a request that is already going to hit the database.
     */
    private const OPEN_FOR_A_REAL_MEMORIAL = [
        'memorial.public',
        'memorial.tribute.public',
        'memorial.chapter.public',
        'memorial.api.',
        'widget.show',
    ];

    /** Whether this request should be turned away because the tenant serving it is suspended. */
    public static function locks(?Reseller $reseller, Request $request): bool
    {
        if (! $reseller || $reseller->isActive()) {
            return false;
        }

        $name = (string) ($request->route()?->getName() ?? '');

        if (self::matches($name, self::ALWAYS_OPEN)) {
            return false;
        }

        if (self::matches($name, self::OPEN_FOR_A_REAL_MEMORIAL)) {
            return ! self::isMemorialOf($reseller, $request);
        }

        // An unnamed route on a suspended tenant is closed. Everything a visitor is meant to
        // reach here has a name, and guessing in favour of access is the wrong way to fail a
        // sanction.
        return true;
    }

    /** @param array<int, string> $prefixes */
    private static function matches(string $name, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the slug on this request name a memorial belonging to this reseller?
     *
     * Scoped to the tenant, not just to the slug: a memorial of *another* reseller must not
     * hold open a door on this one's suspended site.
     */
    private static function isMemorialOf(Reseller $reseller, Request $request): bool
    {
        $slug = $request->route('slug') ?? $request->route('memorial_slug');

        if (! is_string($slug) || $slug === '') {
            return false;
        }

        return Memorial::where('slug', $slug)
            ->where('reseller_id', $reseller->id)
            ->exists();
    }

    /**
     * 503, not 404 or 403.
     *
     * A suspension is meant to end. 404 tells a search engine the funeral home never existed
     * and drops every page it had; 403 reads as "you are not allowed", which blames the
     * visitor for the tenant's billing. 503 says the site is off right now and to come back,
     * which is both true and reversible.
     *
     * Deliberately says nothing about *why*. A visitor is not owed a reseller's account
     * status, and a page reading "suspended for non-payment" is a business's private trouble
     * published on its own front door.
     */
    public static function response(Reseller $reseller): Response
    {
        return response()->view('errors.site-unavailable', [
            'reseller' => $reseller,
            // The homepage is the thing that is closed; offering it here is a loop.
            'hideHomeLink' => true,
        ], 503);
    }
}
