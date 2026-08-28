<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\Theme;
use App\Themes\ThemeCatalogue;
use App\Themes\ThemeRegistry;
use Illuminate\Http\Request;

/**
 * The platform's view of the theme catalogue.
 *
 * A template is a directory in `<project>/themes`; a catalogue row is what makes it
 * selectable. Those two can disagree — a deploy adds a template nobody has synced, or drops
 * one that resellers are still pointing at — and this screen exists mainly to make that
 * disagreement visible. Everything else here is a consequence of it.
 *
 * Deliberately read-mostly. The only writes are publishing a theme and syncing from disk;
 * assigning a theme to a particular reseller belongs on that reseller's own page, next to the
 * rest of what is true about them.
 */
class ThemeController extends Controller
{
    public function index()
    {
        $registry = app(ThemeRegistry::class);
        $manifests = $registry->all();

        // minimumTier eager-loaded: isAvailableTo() reads its sort_order, and this page asks
        // that question once per reseller per card.
        $rows = Theme::whereNull('reseller_id')->with('minimumTier')->orderBy('name')->get()->keyBy('template');

        // One query for the whole page rather than a count per card.
        $usage = Reseller::selectRaw('theme_id, count(*) as total')
            ->whereNotNull('theme_id')
            ->groupBy('theme_id')
            ->pluck('total', 'theme_id');

        // A reseller already running each template, so "how does it look" has an answer that
        // is a real site rather than a mockup. There is no preview-without-a-tenant yet.
        $examples = Reseller::whereNotNull('theme_id')->with('tier')->get()->groupBy('theme_id');

        $templates = collect($manifests)->map(function ($manifest, $template) use ($rows, $usage, $examples) {
            $row = $rows->get($template);

            return [
                'template' => $template,
                'manifest' => $manifest,
                'theme' => $row,
                'in_use' => $row ? (int) ($usage[$row->id] ?? 0) : 0,
                'example' => $row ? $examples->get($row->id)?->first() : null,
                // On disk but never synced: not selectable by anyone until it is.
                'unsynced' => $row === null,
                // Sites already running a theme their plan could not apply today. Not an
                // error — gating never moves a live site — but the number an admin wants
                // before they gate something, and after.
                'below_minimum' => $row
                    ? $examples->get($row->id)?->reject(fn (Reseller $r) => $row->isAvailableTo($r))->count() ?? 0
                    : 0,
            ];
        })->values();

        // Rows pointing at a directory that is not deployed. Their sites still render — in the
        // base design — which is precisely why nobody would notice without being told.
        $orphans = Theme::whereNull('reseller_id')
            ->get()
            ->filter(fn (Theme $t) => $t->templateIsMissing())
            ->map(fn (Theme $t) => ['theme' => $t, 'in_use' => (int) ($usage[$t->id] ?? 0)])
            ->values();

        // What tenants have saved for themselves. Not editable here — it is theirs — but a
        // support question about "our old look" needs somewhere to be answered from.
        $resellerThemes = Theme::whereNotNull('reseller_id')
            ->with('reseller:id,name,slug')
            ->orderBy('reseller_id')
            ->get();

        return view('pages.admin.themes', [
            'title' => 'Themes',
            'templates' => $templates,
            'orphans' => $orphans,
            'resellerThemes' => $resellerThemes,
            'themesPath' => ThemeRegistry::root(),
            // Ordered by sort_order because that is what "and above" means — the select reads
            // as a ladder, which is the only way "minimum" is legible.
            'tiers' => ResellerTier::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Set the lowest plan that may apply a theme.
     *
     * Only the apply action is gated. Resellers below the minimum keep seeing the theme and
     * keep being able to preview it — nobody upgrades for something they have never been
     * shown — and anyone already running it keeps it. See the add_minimum_tier migration.
     */
    public function setMinimumTier(Request $request, Theme $theme)
    {
        abort_unless($theme->isPlatform(), 403, 'Only catalogue themes carry a plan minimum.');

        $data = $request->validate([
            'minimum_tier_id' => ['nullable', 'integer', 'exists:reseller_tiers,id'],
        ]);

        $theme->minimum_tier_id = $data['minimum_tier_id'] ?: null;
        $theme->save();

        $tier = $theme->fresh()->minimumTier;

        $message = $tier
            ? "{$theme->name} now needs the {$tier->name} plan or above to apply."
            : "{$theme->name} is available on every plan again.";

        // The number that decides whether this was the right move, said at the moment it is
        // made rather than found later in a support ticket.
        $stranded = Reseller::where('theme_id', $theme->id)->with('tier')->get()
            ->reject(fn (Reseller $r) => $theme->isAvailableTo($r))->count();

        if ($stranded > 0) {
            $message .= ' '.$stranded.' '.\Illuminate\Support\Str::plural('site', $stranded)
                .' already using it '.($stranded === 1 ? 'is' : 'are').' below that plan and '
                .($stranded === 1 ? 'keeps' : 'keep').' the theme — gating never moves a live site.';
        }

        return redirect()->route('settings.themes')->with('success', $message);
    }

    /** Pick up templates a deploy added, and refresh names and palettes from their manifests. */
    public function sync()
    {
        $result = ThemeCatalogue::sync();

        $created = count($result['created']);

        return redirect()->route('settings.themes')->with('success', $created > 0
            ? $created.' new '.\Illuminate\Support\Str::plural('template', $created).' added to the catalogue.'
            : 'Catalogue is already in step with the templates on disk.');
    }

    /**
     * Take a theme out of the catalogue without deleting it.
     *
     * Unpublishing hides it from every reseller's gallery but leaves the sites already using
     * it exactly as they are — the alternative, moving live sites to another design because an
     * admin toggled a switch, is not something anyone would expect from the word "unpublish".
     */
    public function togglePublished(Request $request, Theme $theme)
    {
        abort_unless($theme->isPlatform(), 403, 'Only catalogue themes can be published or withdrawn.');

        $theme->is_published = ! $theme->is_published;
        $theme->save();

        $inUse = Reseller::where('theme_id', $theme->id)->count();

        $message = $theme->is_published
            ? "{$theme->name} is available to resellers again."
            : "{$theme->name} is hidden from the gallery.";

        if (! $theme->is_published && $inUse > 0) {
            $message .= ' '.$inUse.' '.\Illuminate\Support\Str::plural('site', $inUse)
                .' already using it '.($inUse === 1 ? 'keeps' : 'keep').' it.';
        }

        return redirect()->route('settings.themes')->with('success', $message);
    }
}
