<?php

namespace App\Support;

use App\Models\Page;
use App\Models\SiteLayout;

/**
 * The words over the platform homepage's showcase row, and the one way to change them.
 *
 * This heading does not live in code. It lives in two stored documents — the page-builder Page
 * the homepage actually renders, and the SiteLayout it falls back to — because stored props
 * merge over block defaults. A change to SiteLayoutService reaches a fresh install and nothing
 * else; production keeps showing whatever was stored the day the page was first saved. Every
 * previous change to this copy was therefore a script somebody remembered to run on the
 * server, or did not.
 *
 * So the copy is a constant here, the update is a method here, and a data migration calls it.
 * Deploys run migrations; the homepage changes when the deploy lands, with nobody remembering
 * anything. The script in database/scripts still exists for a manual re-run and delegates to
 * this, so there is exactly one definition of what the row says.
 *
 * Platform only — `whereNull('reseller_id')`. This is our marketing over our showcase; a
 * reseller's page is theirs.
 */
final class PlatformShowcaseCopy
{
    /**
     * 2026-09-07. Requested: drop the paragraph, "Featured" → "Featured Memorials",
     * "Memorial Inspiration" → "Remembering Loved Ones". The block collapses an empty
     * description rather than leaving a gap, so '' is the whole of "remove the text".
     */
    public const CURRENT = [
        'eyebrow' => 'Featured Memorials',
        'title' => 'Remembering Loved Ones',
        'description' => '',
        'cta_label' => 'Create a Memorial',
        'mobile_cta_label' => 'Create a Memorial',
        'cta_route' => 'memorial.create.step1',
    ];

    /**
     * What it said before, kept so the migration is reversible. "Illustrative" rather than
     * "fictional" was a deliberate word in this paragraph — the row pulls any complete public
     * memorial, so the day a real family's page appeared here, "fictional" would have been a
     * false statement about somebody's father. Worth remembering if a paragraph ever returns.
     */
    public const PREVIOUS = [
        'eyebrow' => 'Featured',
        'title' => 'Memorial Inspiration',
        'description' => 'Not sure where to begin? A memorial can be as unique as the life it celebrates. Let these illustrative memorials inspire you to create a beautiful place filled with love, memories, and the moments that made someone special.',
        'cta_label' => 'Create a Memorial',
        'mobile_cta_label' => 'Create a Memorial',
        'cta_route' => 'memorial.create.step1',
    ];

    /** Props from an older shape of the block that would sit in stored JSON meaning nothing. */
    private const DEAD_PROPS = ['view_all_label', 'mobile_view_all_label'];

    /**
     * Write `$copy` onto every memorial_showcase block in both platform stores.
     *
     * Idempotent: a store already carrying the copy is not saved again. Returns how many stores
     * were actually changed, so a caller can say "updated 2" or "already current" truthfully.
     */
    public static function apply(array $copy = self::CURRENT): int
    {
        $touched = 0;

        $page = Page::whereNull('reseller_id')->where('slug', Page::SLUG_VISITOR_HOME)->first();

        if ($page && is_array($page->layout['widgets'] ?? null)) {
            $layout = $page->layout;

            if (self::rewrite($layout['widgets'], $copy)) {
                $page->layout = $layout;
                $page->save();
                $touched++;
            }
        }

        $site = SiteLayout::findPublished(SiteLayout::KEY_VISITOR_HOME);

        if ($site && $site->json) {
            $doc = json_decode($site->json, true) ?: [];

            if (isset($doc['blocks']) && self::rewrite($doc['blocks'], $copy)) {
                $site->json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $site->save();
                $touched++;
            }
        }

        Page::clearSlugCache(Page::SLUG_VISITOR_HOME, null);

        return $touched;
    }

    /**
     * @param  array<int, array{type?: string, props?: array<string, mixed>}>  $blocks  by reference
     */
    private static function rewrite(array &$blocks, array $copy): bool
    {
        $changed = false;

        foreach ($blocks as $i => $block) {
            if (($block['type'] ?? '') !== 'memorial_showcase') {
                continue;
            }

            foreach ($copy as $key => $value) {
                if (($block['props'][$key] ?? null) !== $value) {
                    $blocks[$i]['props'][$key] = $value;
                    $changed = true;
                }
            }

            foreach (self::DEAD_PROPS as $key) {
                if (array_key_exists($key, $block['props'] ?? [])) {
                    unset($blocks[$i]['props'][$key]);
                    $changed = true;
                }
            }
        }

        return $changed;
    }
}
