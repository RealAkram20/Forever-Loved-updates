<?php
/**
 * Point the platform's own homepage showcase at its new copy.
 *
 * The heading lives in stored layouts, not in the block's defaults — stored props merge over
 * defaults, so a code change alone only reaches a fresh install. Both stores are updated: the
 * page-builder Page the homepage actually renders, and the SiteLayout it falls back to.
 *
 * Platform only. `whereNull('reseller_id')` matters: the copy says the memorials underneath are
 * examples rather than real families, which is true of ours and would be false on theirs.
 *
 * Idempotent — a second run reports "already current".
 */
$copy = [
    'eyebrow' => 'Featured',
    'title' => 'Memorial Inspiration',
    'description' => 'Not sure where to begin? A memorial can be as unique as the life it celebrates. Let these fictional examples inspire you to create a beautiful place filled with love, memories, and the moments that made someone special.',
];

$touched = 0;

$page = App\Models\Page::whereNull('reseller_id')->where('slug', App\Models\Page::SLUG_VISITOR_HOME)->first();
if ($page && is_array($page->layout['widgets'] ?? null)) {
    $layout = $page->layout;
    $changed = false;
    foreach ($layout['widgets'] as $i => $w) {
        if (($w['type'] ?? '') !== 'memorial_showcase') { continue; }
        foreach ($copy as $k => $v) {
            if (($w['props'][$k] ?? null) !== $v) {
                echo "  page.{$k}: ".json_encode($w['props'][$k] ?? null).' -> '.json_encode($v).PHP_EOL;
                $layout['widgets'][$i]['props'][$k] = $v;
                $changed = true;
            }
        }
    }
    if ($changed) { $page->layout = $layout; $page->save(); $touched++; }
}

$site = App\Models\SiteLayout::findPublished(App\Models\SiteLayout::KEY_VISITOR_HOME);
if ($site && $site->json) {
    $doc = json_decode($site->json, true);
    $changed = false;
    foreach (($doc['blocks'] ?? []) as $i => $b) {
        if (($b['type'] ?? '') !== 'memorial_showcase') { continue; }
        foreach ($copy as $k => $v) {
            if (($b['props'][$k] ?? null) !== $v) {
                echo "  sitelayout.{$k}: ".json_encode($b['props'][$k] ?? null).' -> '.json_encode($v).PHP_EOL;
                $doc['blocks'][$i]['props'][$k] = $v;
                $changed = true;
            }
        }
    }
    if ($changed) {
        $site->json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $site->save();
        $touched++;
    }
}

App\Models\Page::clearSlugCache(App\Models\Page::SLUG_VISITOR_HOME, null);
echo $touched ? "Updated {$touched} store(s)." : 'Already current.', PHP_EOL;
