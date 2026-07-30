<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Reseller;

/**
 * Gives a reseller a site that already works.
 *
 * Standard pages and menus both started life empty: a new reseller had no About, no Pricing,
 * no Contact and a header that read "Home" and nothing else. Every one of those was a switch
 * they had to find and turn on before their site looked like a business rather than a
 * placeholder — and the pages screen is behind a tier flag, so some of them could not reach
 * the switches at all.
 *
 * So the default is *on*: every standard page published, and a header and two footer columns
 * that link them, mirroring the platform's own arrangement.
 *
 * Every step is idempotent and additive. It creates what is missing and never touches what is
 * already there — a reseller who has deliberately switched their pricing page off, renamed a
 * link or reordered a menu must not have that undone by a later run, whether that run comes
 * from the backfill migration, a second call, or a support fix.
 */
class ResellerSiteProvisioner
{
    /**
     * @param  bool  $appendMissingLinks  Add the default links a menu is missing instead of
     *                                    skipping a location that already exists. True only
     *                                    for the one-off backfill — see ensureDefaultMenus().
     */
    public static function provision(Reseller $reseller, bool $appendMissingLinks = false): void
    {
        self::ensureStandardPages($reseller);
        self::ensureDefaultMenus($reseller, $appendMissingLinks);
    }

    /**
     * Every standard page, published.
     *
     * Only rows that do not exist yet are created, so an off switch stays off.
     */
    public static function ensureStandardPages(Reseller $reseller): void
    {
        $existing = Page::where('reseller_id', $reseller->id)->pluck('slug')->all();

        foreach (StandardPages::catalogue() as $slug => $definition) {
            if (in_array($slug, $existing, true)) {
                continue;
            }

            Page::create([
                'reseller_id' => $reseller->id,
                'slug' => $slug,
                'title' => $definition['title'],
                'content' => self::starterContent($reseller, $slug, $definition),
                'layout' => null,
                'is_published' => true,
            ]);

            Page::clearSlugCache($slug, $reseller->id);
        }
    }

    /**
     * @param  array{title: string, kind: string, disableable: bool, seed_from_platform: bool, blurb: string}  $definition
     */
    private static function starterContent(Reseller $reseller, string $slug, array $definition): ?string
    {
        // Legal text starts as ours, so their site is never published without a policy.
        if ($definition['seed_from_platform']) {
            return Page::getBySlug($slug)?->content;
        }

        // The dynamic pages (pricing, contact, the directory) draw their own content from
        // plans, a form and a search, so they read correctly with none.
        if ($definition['kind'] !== StandardPages::KIND_CONTENT) {
            return null;
        }

        // Which leaves About, the one page that would otherwise publish blank. A starter in
        // their own name beats an empty page, and beats our story on their domain.
        $name = e($reseller->name);

        return "<p>{$name} helps families create lasting online memorials for the people they love.</p>"
            .'<p>Replace this text with your own story — who you are, how long you have served your '
            .'community, and how families can reach you.</p>';
    }

    /**
     * Header and the two footer columns, mirroring the platform's own arrangement.
     *
     * A location the reseller already has is normally left completely alone, including an
     * empty one: having deleted every link from their header is a decision, and a later run
     * of this must not undo it.
     *
     * $appendMissingLinks relaxes that for the one-off backfill, and only there. A menu built
     * before standard pages existed cannot have had these links removed from it — there was
     * nothing to remove — so adding what is missing is filling a gap rather than overriding a
     * choice. New links go on the end; existing ones keep their order and their labels.
     */
    public static function ensureDefaultMenus(Reseller $reseller, bool $appendMissingLinks = false): void
    {
        foreach (self::defaultMenus() as $location => $items) {
            $menu = Menu::query()->forTenant($reseller->id)->where('location', $location)->first();

            if ($menu && ! $appendMissingLinks) {
                continue;
            }

            $menu ??= Menu::create(['reseller_id' => $reseller->id, 'location' => $location]);

            $linked = $menu->allItems()->get()
                ->map(fn (MenuItem $item) => self::targetOf($item->route_name, $item->route_parameters))
                ->filter()
                ->all();

            $order = (int) $menu->allItems()->whereNull('parent_id')->max('sort_order');

            foreach ($items as $item) {
                $target = isset($item['route'])
                    ? self::targetOf($item['route'], null)
                    : self::targetOf('cms.page', ['slug' => $item['page']]);

                if (in_array($target, $linked, true)) {
                    continue;
                }

                $linked[] = $target;

                MenuItem::create([
                    'menu_id' => $menu->id,
                    'parent_id' => null,
                    'label' => $item['label'],
                    'route_name' => $item['route'] ?? 'cms.page',
                    'route_parameters' => isset($item['route']) ? null : ['slug' => $item['page']],
                    'url' => null,
                    'open_in_new_tab' => false,
                    'sort_order' => ++$order,
                ]);
            }
        }
    }

    /**
     * Platform route names that lead to the same place as a standard page.
     *
     * A menu built before standard pages existed links these by route, since that was the
     * only way to reach them. `route=home` and `cms.page::visitor-home` are one destination
     * written two ways, and without this the backfill reads them as different and appends a
     * second "Home" beside the reseller's own.
     */
    private const ROUTE_EQUIVALENTS = [
        'home' => Page::SLUG_VISITOR_HOME,
        'about' => 'about',
        'pricing' => 'pricing',
        'contact' => 'contact',
        'memorial.directory' => Page::SLUG_FIND_MEMORIAL,
        'privacy-policy' => 'privacy-policy',
        'terms-of-use' => 'terms-of-use',
    ];

    /** A comparable identity for whatever a menu item points at. */
    private static function targetOf(?string $routeName, ?array $routeParameters): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        if ($routeName === 'cms.page') {
            $slug = $routeParameters['slug'] ?? null;

            return is_string($slug) && $slug !== '' ? 'page:'.$slug : null;
        }

        return isset(self::ROUTE_EQUIVALENTS[$routeName])
            ? 'page:'.self::ROUTE_EQUIVALENTS[$routeName]
            : 'route:'.$routeName;
    }

    /**
     * The default navigation.
     *
     * `page` entries resolve through the reseller's own address (MenuItem::resolvedUrl), so
     * they follow their subdomain or custom domain. `route` is used only where the
     * destination is genuinely shared — the create-memorial flow, which stamps the tenant on
     * the new account from the host it was reached on.
     *
     * @return array<string, list<array{label: string, page?: string, route?: string}>>
     */
    public static function defaultMenus(): array
    {
        return [
            Menu::LOCATION_HEADER => [
                ['label' => 'Home', 'page' => Page::SLUG_VISITOR_HOME],
                ['label' => 'About', 'page' => 'about'],
                ['label' => 'Pricing', 'page' => 'pricing'],
                ['label' => 'Find Memorial', 'page' => Page::SLUG_FIND_MEMORIAL],
                ['label' => 'Contact', 'page' => 'contact'],
            ],
            Menu::LOCATION_FOOTER_QUICK => [
                ['label' => 'Home', 'page' => Page::SLUG_VISITOR_HOME],
                ['label' => 'Find Memorial', 'page' => Page::SLUG_FIND_MEMORIAL],
                ['label' => 'Create Memorial', 'route' => 'memorial.create.step1'],
                ['label' => 'Pricing', 'page' => 'pricing'],
            ],
            Menu::LOCATION_FOOTER_COMPANY => [
                ['label' => 'About Us', 'page' => 'about'],
                ['label' => 'Contact Us', 'page' => 'contact'],
                ['label' => 'Privacy Policy', 'page' => 'privacy-policy'],
                ['label' => 'Terms of Use', 'page' => 'terms-of-use'],
            ],
        ];
    }
}
