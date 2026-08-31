<?php

namespace App\Http\Controllers\Reseller;

use App\Helpers\ThemeSetting;
use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\Theme;
use App\Themes\ThemeCatalogue;
use App\Themes\ThemePreview;
use App\Themes\ThemeRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Which design a reseller's public site wears.
 *
 * Sits beside Appearance rather than inside it, because the two answer different questions and
 * a reseller reaches for them at different times. Appearance is "what colour is our button";
 * this is "what does our site look like". Folding the second into the thirty-colour form would
 * bury the only control most resellers actually want.
 *
 * The relationship between them is deliberate and is stated on the page: a theme sets a
 * palette, and anything the reseller set by hand still wins. Applying a theme therefore never
 * discards work — see ThemeSetting::get().
 */
class ThemeController extends Controller
{
    public function index(Request $request)
    {
        $reseller = $request->user()->reseller;

        $themes = Theme::selectableFor($reseller?->id);
        $active = $this->activeTheme($reseller);

        return view('pages.reseller.theme', [
            'title' => 'Theme',
            'reseller' => $reseller,
            'themes' => $themes,
            'active' => $active,
            'registry' => app(ThemeRegistry::class),
            // How many of their own colours are sitting on top of the active theme. Named on
            // the page so the one confusing state of the layering — "I applied a theme and
            // half of it did not take" — is answered before it is asked.
            'shadowedCount' => $this->shadowedCount($reseller, $active),
            // The same question for pages. A reseller who already had a home page keeps it
            // when they apply a theme, which is right and was also completely silent — so the
            // theme's designed front page looked like something that had never been built.
            'keptPages' => $this->keptPages($reseller, $active),
        ]);
    }

    public function apply(Request $request)
    {
        $reseller = $this->resellerOrFail($request);

        $data = $request->validate(['theme_id' => ['required', 'integer']]);

        $theme = Theme::selectableFor($reseller->id)->firstWhere('id', (int) $data['theme_id']);

        if (! $theme) {
            // Not a 404: selectableFor() is the authorisation boundary, and the id may well
            // exist — as another tenant's saved theme. Same answer either way.
            throw ValidationException::withMessages([
                'theme_id' => 'That theme is not available to you.',
            ]);
        }

        // The plan gate, and the only place it applies. Everything else about a gated theme —
        // seeing it, previewing it — stays open, because a reseller cannot want an upgrade
        // they have never been shown. Named rather than generic: "not available to you" would
        // read as a bug to somebody who can see the card and press the button.
        if (! $theme->isAvailableTo($reseller)) {
            throw ValidationException::withMessages([
                'theme_id' => "{$theme->name} is available on the {$theme->minimumTier?->name} plan and above. "
                    .'Your current plan cannot apply it, but you can keep previewing it.',
            ]);
        }

        $reseller->theme_id = $theme->id;
        $reseller->save();

        ThemeSetting::forgetThemeTokens($reseller->id);

        // Hand them the template's pages as documents they can open, but only where they have
        // not built one themselves. Applying a theme must never cost someone an afternoon.
        $manifest = app(\App\Themes\ThemeRegistry::class)->manifest($theme->templateSlug());

        $seeded = \App\Themes\ThemePages::seed($reseller, $manifest);

        $message = "Your site is now using {$theme->name}.".\App\Themes\ThemePages::summary($seeded);

        // The other half of the sentence summary() was always meant to say. Without it, a
        // reseller who already had a home page sees their old front page in new colours and
        // concludes the theme is broken — which is exactly what happened, and cost an
        // afternoon of looking for a bug in a template that was working correctly.
        $kept = \App\Themes\ThemePages::keptByOwner($reseller, $manifest);

        if ($kept !== []) {
            $names = array_map(
                fn (string $slug) => \App\Support\StandardPages::definition($slug)['title'] ?? $slug,
                $kept,
            );

            $message .= ' Your own '.implode(', ', $names).' '
                .(count($names) === 1 ? 'page was' : 'pages were')
                .' kept, so '.(count($names) === 1 ? 'it is' : 'they are').' still exactly as you built '
                .(count($names) === 1 ? 'it' : 'them').". You can swap {$theme->name}'s design in below.";
        }

        if ($theme->templateIsMissing()) {
            $message .= ' Its template is not deployed on this server yet, so it is rendering'
                .' in the base design until it is.';
        }

        return redirect()->route('reseller.theme')->with('success', $message);
    }

    /**
     * Replace one of this reseller's pages with the active theme's design for it.
     *
     * The escape hatch from rule 1 of ThemePages: applying a theme never overwrites a page
     * somebody built, which is right, and leaves them no way to ever get the theme's version.
     * This is that way — asked for by name, one page at a time, never as a side effect.
     *
     * Their own layout is overwritten and not recoverable from here, so the button that reaches
     * this says so. A page they have not built is not offered at all: seeding already gave them
     * the theme's design, so there is nothing to swap.
     */
    public function resetPage(Request $request)
    {
        $reseller = $this->resellerOrFail($request);

        $data = $request->validate(['slug' => ['required', 'string', 'max:90']]);

        $theme = $this->activeTheme($reseller);

        abort_unless($theme !== null, 404);

        $manifest = app(ThemeRegistry::class)->manifest($theme->templateSlug());

        // Only a page this theme actually ships, and only one it is currently keeping. Without
        // both checks the slug is a free-form string that could point at any page they own.
        if (! in_array($data['slug'], \App\Themes\ThemePages::keptByOwner($reseller, $manifest), true)) {
            throw ValidationException::withMessages([
                'slug' => 'That page is not one this theme can replace.',
            ]);
        }

        $title = \App\Support\StandardPages::definition($data['slug'])['title'] ?? $data['slug'];

        if (! \App\Themes\ThemePages::resetToTheme($reseller, $manifest, $data['slug'])) {
            return redirect()->route('reseller.theme')
                ->with('error', "{$theme->name} could not supply a {$title} page. Nothing was changed.");
        }

        return redirect()->route('reseller.theme')
            ->with('success', "Your {$title} page now uses {$theme->name}'s design. Edit it in the page builder.");
    }

    /**
     * Look at a theme on the live site without applying it.
     *
     * All this does is mint a short-lived signed link and send them to it. The preview itself
     * is a flag in the session on the *tenant's* host, which is a different host from this one
     * — and a different domain once they verify their own — so it cannot be set from here.
     * See App\Themes\ThemePreview for why it is a session flag and not a `?theme=` parameter.
     *
     * Authorisation happens twice on purpose: here, because only their own staff may mint a
     * link, and again on arrival against the tenant the host resolved, because a signature
     * only proves the link came from us, not who is holding it now.
     */
    public function preview(Request $request)
    {
        $reseller = $this->resellerOrFail($request);

        $data = $request->validate(['theme_id' => ['required', 'integer']]);

        $theme = Theme::selectableFor($reseller->id)->firstWhere('id', (int) $data['theme_id']);

        if (! $theme) {
            throw ValidationException::withMessages([
                'theme_id' => 'That theme is not available to you.',
            ]);
        }

        return redirect(ThemePreview::linkFor($reseller, $theme));
    }

    /**
     * Snapshot the look currently in force as a named, reusable theme of their own.
     *
     * Deliberately does not clear the reseller_settings it copies. Clearing would make the
     * save visually lossless only if the snapshot were perfect, and a save button that can
     * change how your site looks is a save button nobody trusts. The copy is inert until it
     * is applied somewhere else — a second brand, a rebrand they want to be able to undo.
     */
    public function save(Request $request)
    {
        $reseller = $this->resellerOrFail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $theme = Theme::create([
            'reseller_id' => $reseller->id,
            'name' => $data['name'],
            'slug' => ThemeCatalogue::uniqueSlugFor($reseller->id, $data['name']),
            'template' => $reseller->templateSlug(),
            'tokens' => $this->currentTokens($reseller),
            'is_published' => true,
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('reseller.theme')
            ->with('success', "Saved “{$theme->name}”. It is yours to apply whenever you want it.");
    }

    public function destroy(Request $request, Theme $theme)
    {
        $reseller = $this->resellerOrFail($request);

        // Only their own. The platform catalogue is not theirs to remove, and another
        // tenant's is not theirs to see.
        abort_unless($theme->reseller_id === $reseller->id, 403);

        if ($reseller->theme_id === $theme->id) {
            // Deleting the theme a site is running would move that site, via nullOnDelete,
            // with no warning and no obvious cause. Make them switch first.
            return redirect()->route('reseller.theme')
                ->withErrors(['theme' => 'That is the theme your site is using. Apply a different one first.']);
        }

        $name = $theme->name;
        $theme->delete();

        return redirect()->route('reseller.theme')->with('success', "Deleted “{$name}”.");
    }

    /**
     * What they are running: their chosen theme, or the base catalogue row, which is what a
     * reseller who has never chosen anything has been running all along.
     */
    private function activeTheme(?Reseller $reseller): ?Theme
    {
        return $reseller?->theme ?? ThemeCatalogue::base();
    }

    /**
     * Every appearance value this reseller has set by hand — reseller_settings rows plus the
     * column-backed keys. The same union Reseller\AppearanceController::resellerValues()
     * builds, and for the same reason: reading only one of the two contradicts the site.
     *
     * @return array<string, string>
     */
    private function currentTokens(Reseller $reseller): array
    {
        $out = [];

        foreach (ThemeSetting::columnAliases() as $key => $column) {
            // Logos and the favicon are uploaded files belonging to this tenant, not part of
            // a reusable look — a theme carrying a path into their storage would break the
            // moment it was applied anywhere else.
            if ($key === 'branding.primary_color' && filled($reseller->{$column})) {
                $out[$key] = (string) $reseller->{$column};
            }
        }

        foreach (ResellerSetting::allFor($reseller->id) as $key => $row) {
            $out[$key] = (string) ($row['value'] ?? '');
        }

        return $out;
    }

    private function shadowedCount(?Reseller $reseller, ?Theme $theme): int
    {
        if (! $reseller || ! $theme) {
            return 0;
        }

        return count(array_intersect_key($this->currentTokens($reseller), $theme->tokenValues()));
    }

    /**
     * The pages of the active theme this reseller kept because they had already built them.
     *
     * @return array<int, array{slug: string, title: string}>
     */
    private function keptPages(?Reseller $reseller, ?Theme $theme): array
    {
        if (! $reseller || ! $theme) {
            return [];
        }

        $manifest = app(ThemeRegistry::class)->manifest($theme->templateSlug());

        return array_map(fn (string $slug) => [
            'slug' => $slug,
            'title' => \App\Support\StandardPages::definition($slug)['title'] ?? $slug,
        ], \App\Themes\ThemePages::keptByOwner($reseller, $manifest));
    }

    private function resellerOrFail(Request $request): Reseller
    {
        $reseller = $request->user()->reseller;

        abort_unless($reseller !== null, 403);

        return $reseller;
    }
}
