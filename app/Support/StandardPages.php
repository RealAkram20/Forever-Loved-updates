<?php

namespace App\Support;

use App\Helpers\ThemeSetting;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

/**
 * The pages every site has, as opposed to the ones somebody builds.
 *
 * The platform ships seven; a reseller used to get one. Four of the rest redirected to their
 * front page and two served *our* legal text on their domain, because menus and pages alike
 * only ever existed in a single platform-wide copy. The clearest symptom: a reseller in the
 * database has a page called `about-us`, because `Page::reservedSlugs()` refused them `about`.
 *
 * This is the catalogue that lets a tenant switch each one on. It is deliberately a flat
 * declaration rather than rows in a table: the set is fixed by the routes that serve it —
 * adding an entry here without a controller behind it would produce a toggle that turns on a
 * 404 — and keeping it in code means the seeding, the labels and the enablement rules cannot
 * drift apart across tenants.
 */
class StandardPages
{
    /** Rendered from `content` (or a page-builder layout, if the owner adds one). */
    public const KIND_CONTENT = 'content';

    /** Backed by a controller that supplies live data — plans, a form, a search. */
    public const KIND_DYNAMIC = 'dynamic';

    /**
     * slug => definition.
     *
     * `disableable: false` is not an oversight in either case. A site with no front page is
     * broken, and one with no privacy policy is worse than one carrying generic text — which
     * is exactly why the legal pages are seeded from the platform's own wording rather than
     * left blank for someone to forget.
     *
     * @return array<string, array{title: string, kind: string, disableable: bool, seed_from_platform: bool, blurb: string}>
     */
    public static function catalogue(): array
    {
        return [
            Page::SLUG_VISITOR_HOME => [
                'title' => 'Home',
                'kind' => self::KIND_DYNAMIC,
                'disableable' => false,
                'seed_from_platform' => true,
                'blurb' => 'The front page of your site.',
            ],
            'about' => [
                'title' => 'About Us',
                'kind' => self::KIND_CONTENT,
                'disableable' => true,
                'seed_from_platform' => false,
                'blurb' => 'Who you are and what you do.',
            ],
            'pricing' => [
                'title' => 'Pricing',
                'kind' => self::KIND_DYNAMIC,
                'disableable' => true,
                'seed_from_platform' => false,
                'blurb' => 'Lists your own plans, at your own prices.',
            ],
            'contact' => [
                'title' => 'Contact',
                'kind' => self::KIND_DYNAMIC,
                'disableable' => true,
                'seed_from_platform' => false,
                'blurb' => 'A contact form that reaches your inbox.',
            ],
            Page::SLUG_FIND_MEMORIAL => [
                'title' => 'Find a Memorial',
                'kind' => self::KIND_DYNAMIC,
                'disableable' => true,
                'seed_from_platform' => false,
                'blurb' => 'Lets families search the memorials you host.',
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'kind' => self::KIND_CONTENT,
                'disableable' => false,
                'seed_from_platform' => true,
                'blurb' => 'Starts from ours — edit it to describe your own practices.',
            ],
            'terms-of-use' => [
                'title' => 'Terms of Use',
                'kind' => self::KIND_CONTENT,
                'disableable' => false,
                'seed_from_platform' => true,
                'blurb' => 'Starts from ours — edit it to describe your own terms.',
            ],
        ];
    }

    /**
     * Pages that fall back to the platform's copy when a tenant has none of their own.
     *
     * Only the legal ones, and for the reason already written into ResolveResellerByHost:
     * we really are the data processor, and serving no privacy policy at all is worse than
     * serving ours. A reseller who has never opened the Pages screen has no rows yet, and
     * their site must not answer /privacy-policy with a redirect to the homepage.
     *
     * Everything else is theirs or absent — a reseller's site showing *our* About page would
     * be the white-label leak this whole area exists to prevent.
     *
     * @return list<string>
     */
    public static function fallsBackToPlatform(): array
    {
        return ['privacy-policy', 'terms-of-use'];
    }

    /**
     * The page to serve for this slug on this tenant's site, or null when the site should
     * not answer that path at all.
     *
     * Passing null for $resellerId asks the same question for the platform's own host.
     */
    public static function resolve(string $slug, ?int $resellerId): ?Page
    {
        if ($resellerId === null) {
            return Page::publishedFor($slug, null);
        }

        $page = Page::publishedFor($slug, $resellerId);

        if ($page) {
            return $page;
        }

        return in_array($slug, self::fallsBackToPlatform(), true)
            ? Page::publishedFor($slug, null)
            : null;
    }

    /** Whether this tenant's site should answer this path at all. */
    public static function isEnabledFor(string $slug, ?int $resellerId): bool
    {
        return self::resolve($slug, $resellerId) !== null;
    }

    /**
     * The route name the platform serves each standard page at.
     *
     * Only the ones a page-builder block is allowed to point a button at; the legal pages
     * and the home page are never a configured destination.
     */
    private const PLATFORM_ROUTES = [
        'about' => 'about',
        'pricing' => 'pricing',
        'contact' => 'contact',
        Page::SLUG_FIND_MEMORIAL => 'memorial.directory',
    ];

    /**
     * The address of a standard page on the site currently being served.
     *
     * See SiteUrl for why route() cannot be asked this. The platform keeps going through
     * route() where one exists, so a future change to where /find-memorial actually lives
     * only has to be made in the route file.
     */
    public static function urlFor(string $slug): string
    {
        if (ThemeSetting::siteTenant()) {
            return SiteUrl::to($slug);
        }

        $route = self::PLATFORM_ROUTES[$slug] ?? null;

        return $route && Route::has($route) ? route($route) : SiteUrl::to($slug);
    }

    /**
     * The same answer for a stored route name, which is how the hero and CTA blocks record
     * where their buttons go.
     *
     * A block may legitimately point anywhere, so anything outside the standard set falls
     * through to route(); an unknown name returns null so callers can render '#' as before.
     */
    public static function urlForRouteName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        $slug = array_search($name, self::PLATFORM_ROUTES, true);

        if ($slug !== false) {
            return self::urlFor($slug);
        }

        return Route::has($name) ? route($name) : null;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::catalogue());
    }

    public static function isStandard(?string $slug): bool
    {
        return $slug !== null && array_key_exists($slug, self::catalogue());
    }

    /** @return array{title: string, kind: string, disableable: bool, seed_from_platform: bool, blurb: string}|null */
    public static function definition(string $slug): ?array
    {
        return self::catalogue()[$slug] ?? null;
    }

    /**
     * Whether a tenant is allowed to switch this page off.
     *
     * Unknown slugs answer true: a custom page is always the owner's to unpublish, and only
     * the fixed set above carries the "your site would be broken without this" rule.
     */
    public static function isDisableable(string $slug): bool
    {
        return self::definition($slug)['disableable'] ?? true;
    }

    /**
     * Slugs a reseller may own even though Page::reservedSlugs() forbids them in the
     * free-form create form.
     *
     * The reservation still matters — it stops somebody hand-building a second `pricing`
     * that shadows the real one — so it stays in place everywhere except the toggle, which
     * is the single path allowed to mint these.
     *
     * @return list<string>
     */
    public static function toggleableSlugs(): array
    {
        return array_values(array_filter(
            self::slugs(),
            fn (string $slug) => self::isDisableable($slug) || $slug !== Page::SLUG_VISITOR_HOME
        ));
    }
}
