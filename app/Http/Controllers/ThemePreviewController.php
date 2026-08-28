<?php

namespace App\Http\Controllers;

use App\Helpers\ThemeSetting;
use App\Models\Theme;
use App\Support\SiteUrl;
use App\Themes\ThemePreview;
use Illuminate\Http\Request;

/**
 * The two ends of a preview, both served on the *tenant's* host.
 *
 * They have to be. The flag lives in the session, sessions are host-scoped here
 * (SESSION_DOMAIN is null), and the site being previewed is on a different host from the
 * dashboard that asked for it — a different domain entirely once a reseller verifies their
 * own. So the dashboard mints a short-lived signed link and the work happens on arrival.
 *
 * Deliberately unauthenticated. There is no session on this host to authenticate against yet;
 * that is the problem the signed link exists to solve. The signature is the credential, and
 * `enter` re-derives everything else from the tenant the host resolved rather than trusting
 * anything the URL says about who is asking.
 */
class ThemePreviewController extends Controller
{
    /**
     * Start previewing, having arrived on a signed link from the dashboard.
     *
     * The link carries a theme id and nothing else. Whether that theme may be shown on this
     * site is decided here, against the tenant the *host* resolved — so a link minted for one
     * reseller cannot start a preview on another's site even if the signature were somehow
     * still good.
     */
    public function enter(Request $request)
    {
        $reseller = ThemeSetting::siteTenant();

        // No tenant means this is the platform's own host, where there is nothing to preview.
        abort_unless($reseller !== null, 404);

        // Read by name rather than taken as a method argument. Laravel hands scalar route
        // parameters to a controller *positionally*, and the two routes that reach here do not
        // agree on position: the /r/{slug} fallback puts {reseller} first, so a `string $theme`
        // argument was handed the reseller's slug and every preview 403'd — on that route only,
        // which is the one development actually uses.
        $selected = Theme::selectableFor($reseller->id)
            ->firstWhere('id', (int) $request->route('theme'));

        // Not a 404: selectableFor() is the authorisation boundary and the id may well exist,
        // as another tenant's saved theme. Same answer either way — the same reasoning as
        // Reseller\ThemeController::apply().
        abort_unless($selected !== null, 403);

        ThemePreview::start($request, $reseller, $selected);

        return redirect(SiteUrl::to('/'));
    }

    /**
     * Stop previewing.
     *
     * A GET, and unsigned, because the only thing it can do is end the previewer's own
     * preview — there is nothing here for a forged request to achieve that the person could
     * not do by closing the tab. Making it a POST would mean a form, and a form would mean a
     * CSRF token welded into a bar that is injected into somebody else's template.
     */
    public function stop(Request $request)
    {
        ThemePreview::stop($request);

        return redirect(SiteUrl::to('/'));
    }
}
