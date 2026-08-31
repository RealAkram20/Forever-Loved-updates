<?php

namespace App\Themes;

use App\Models\Page;
use App\Models\Reseller;
use App\Services\PageLayoutService;
use App\Support\StandardPages;
use Illuminate\Support\Facades\Log;

/**
 * Hands a reseller the pages a template ships, as documents they can open and edit.
 *
 * This is what makes "resellers can edit this website" literally true rather than
 * aspirational. Without it, applying a theme changes the *rendering* of pages a reseller never
 * built — so the front page they see is one thing and the page in their builder is blank, and
 * the first edit they make replaces a designed page with an empty one.
 *
 * Two rules, both load-bearing:
 *
 *  1. **Never overwrite work.** A page that already has a layout is theirs. Applying a theme,
 *     re-applying it, or a later backfill must not undo an afternoon someone spent. The
 *     provisioner already follows this rule for pages and menus; this follows it for layouts.
 *  2. **Never write a document the builder cannot open.** Every document is validated through
 *     PageLayoutService first, exactly as a save from the editor would be. A template with a
 *     typo in its manifest fails here, loudly, instead of becoming a 500 on someone's front
 *     page.
 */
class ThemePages
{
    /**
     * Seed every page this template ships that the reseller has not built themselves.
     *
     * @return array<int, string> the slugs actually seeded
     */
    public static function seed(Reseller $reseller, ?ThemeManifest $manifest): array
    {
        if ($manifest === null || $manifest->defaultPages === []) {
            return [];
        }

        $seeded = [];

        foreach ($manifest->defaultPages as $slug => $document) {
            if (self::seedOne($reseller, $slug, $document, $manifest->template)) {
                $seeded[] = $slug;
            }
        }

        return $seeded;
    }

    /**
     * @param  array{widgets: array<int, array<string, mixed>>}  $document
     */
    private static function seedOne(Reseller $reseller, string $slug, array $document, string $template): bool
    {
        $page = Page::where('reseller_id', $reseller->id)->where('slug', $slug)->first();

        if (! $page) {
            // A template may bring pages the platform has no concept of — a Services listing,
            // a page per service — and those have to be created or the links in its own
            // navigation lead nowhere. A standard page the reseller has switched off has no
            // title in the manifest, so it stays off: turning someone's page back on because
            // they changed theme would be the theme editing their site rather than dressing it.
            $title = $document['title'] ?? null;

            if (! is_string($title) || trim($title) === '') {
                return false;
            }

            if (! self::mayCreate($reseller, $slug)) {
                return false;
            }

            $page = Page::create([
                'reseller_id' => $reseller->id,
                'slug' => $slug,
                'title' => $title,
                'is_published' => true,
            ]);
        }

        // Theirs already — applying a theme must never cost someone an afternoon.
        if ($page->hasLayout()) {
            return false;
        }

        try {
            $validated = app(PageLayoutService::class)->validateDocumentFromArray($document);
        } catch (\Throwable $e) {
            // A broken manifest must not stop someone applying a theme; it just means that
            // page starts empty, and the reason is recorded where an operator can find it.
            Log::warning('Theme template shipped an invalid page document.', [
                'template' => $template,
                'slug' => $slug,
                'reason' => $e->getMessage(),
            ]);

            return false;
        }

        $page->layout = $validated;
        $page->save();

        Page::clearSlugCache($slug, $reseller->id);

        return true;
    }

    /**
     * The pages this template ships that the reseller already had, so seeding left them alone.
     *
     * The silent half of rule 1. Keeping someone's work is right; keeping it without saying so
     * is what makes a freshly applied theme look half-applied — the reseller sees the new
     * palette on their old front page and reasonably concludes the theme is broken. This is the
     * pages equivalent of the shadowed-colours count the theme screen already shows, and exists
     * for the same reason: name the confusing state before it is discovered.
     *
     * Only pages the template actually ships a document for. A page they built that this
     * template has no opinion about is not being "kept" from anything.
     *
     * @return array<int, string> slugs, in the order the manifest lists them
     */
    public static function keptByOwner(Reseller $reseller, ?ThemeManifest $manifest): array
    {
        if ($manifest === null || $manifest->defaultPages === []) {
            return [];
        }

        // One query rather than one per page: a template may ship a dozen, and this runs on
        // every render of the theme screen.
        $pages = Page::query()
            ->where('reseller_id', $reseller->id)
            ->whereIn('slug', array_keys($manifest->defaultPages))
            ->get(['slug', 'layout'])
            ->filter(fn (Page $page) => $page->hasLayout())
            ->keyBy('slug');

        $out = [];

        foreach ($manifest->defaultPages as $slug => $document) {
            $page = $pages->get($slug);

            if (! $page) {
                continue;
            }

            // A page that already *is* the template's document is not being kept from
            // anything — it is what seeding put there. Without this, applying a theme to a
            // fresh site offered to replace every page with the design it had just installed,
            // which reads as the feature being broken rather than as an offer.
            if (self::matchesTemplate($page->layout, $document, $manifest->template, $slug)) {
                continue;
            }

            $out[] = $slug;
        }

        return $out;
    }

    /**
     * Whether a stored layout is the template's own document rather than something edited.
     *
     * Compared after the manifest goes through the same validator a save does, so defaults and
     * ordering are filled in on both sides — the raw manifest never equals a stored document.
     *
     * Widget ids are stripped first. They are minted fresh on every validation, so two
     * identical documents never share them, and comparing with them in makes this answer "no"
     * always. Everything else — type, order and every prop — must match exactly, so a reseller
     * who changed one word still counts as having their own page.
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $document
     */
    private static function matchesTemplate(array $stored, array $document, string $template, string $slug): bool
    {
        try {
            $validated = app(PageLayoutService::class)->validateDocumentFromArray($document);
        } catch (\Throwable $e) {
            // A manifest this broken cannot be offered as a replacement either, so treating it
            // as "not a match" would advertise a swap that resetToTheme() would then refuse.
            Log::warning('Theme template shipped an invalid page document.', [
                'template' => $template,
                'slug' => $slug,
                'reason' => $e->getMessage(),
            ]);

            return true;
        }

        return self::withoutIds($stored) == self::withoutIds($validated);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int, array<string, mixed>>
     */
    private static function withoutIds(array $document): array
    {
        return array_map(static function (array $widget): array {
            unset($widget['id']);

            return $widget;
        }, $document['widgets'] ?? []);
    }

    /**
     * Replace one page with the document this template ships for it.
     *
     * The deliberate opposite of seed(), and the only way a reseller can get a template's
     * designed page once they have a page of their own there. Never called by applying a
     * theme — only when somebody asks for it by name, having been told what it will cost.
     *
     * Validated through PageLayoutService exactly as seed() and an editor save are, so a
     * manifest typo fails here rather than becoming a 500 on their front page.
     */
    public static function resetToTheme(Reseller $reseller, ?ThemeManifest $manifest, string $slug): bool
    {
        $document = $manifest?->defaultPages[$slug] ?? null;

        if ($document === null) {
            return false;
        }

        $page = Page::where('reseller_id', $reseller->id)->where('slug', $slug)->first();

        if (! $page) {
            // Nothing to replace — seed() is the right route for a page that does not exist,
            // and it applies the reserved-slug and memorial-collision rules this must not skip.
            return self::seedOne($reseller, $slug, $document, $manifest->template);
        }

        try {
            $validated = app(PageLayoutService::class)->validateDocumentFromArray($document);
        } catch (\Throwable $e) {
            Log::warning('Theme template shipped an invalid page document.', [
                'template' => $manifest->template,
                'slug' => $slug,
                'reason' => $e->getMessage(),
            ]);

            return false;
        }

        $page->layout = $validated;
        $page->save();

        Page::clearSlugCache($slug, $reseller->id);

        return true;
    }

    /**
     * Whether a template may claim this slug on this tenant's site.
     *
     * Two ways it may not. A reserved slug is a path the application already answers —
     * `/login`, `/pricing`, `/dashboard` — and a page there is unreachable, so creating one
     * produces a row in the page list that can never be viewed. And a slug already taken by
     * one of this reseller's memorials would make that memorial unreachable instead, which is
     * the worse direction: a family's page disappearing because a funeral home changed theme.
     */
    private static function mayCreate(Reseller $reseller, string $slug): bool
    {
        if (! preg_match('/^[a-z0-9][a-z0-9\-]{0,80}$/', $slug)) {
            return false;
        }

        if (in_array($slug, Page::reservedSlugs(), true)) {
            return false;
        }

        return ! \App\Models\Memorial::where('reseller_id', $reseller->id)
            ->where('slug', $slug)
            ->exists();
    }

    /**
     * A one-line summary for the flash message after applying a theme.
     *
     * Worth saying out loud: a reseller who has already built their home page needs to know
     * why the new theme's front page is not the one in the screenshot, and a reseller who has
     * not needs to know the pages are now sitting in their builder.
     *
     * @param  array<int, string>  $seeded
     */
    public static function summary(array $seeded): string
    {
        if ($seeded === []) {
            return '';
        }

        $names = array_map(
            fn (string $slug) => StandardPages::definition($slug)['title'] ?? $slug,
            $seeded,
        );

        $count = count($names);

        return ' Its '.($count === 1 ? 'page' : 'pages').' — '.implode(', ', $names)
            .' — '.($count === 1 ? 'is' : 'are').' now in your page builder, ready to edit.';
    }
}
