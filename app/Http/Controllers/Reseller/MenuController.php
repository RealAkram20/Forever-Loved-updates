<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Reseller;
use App\Support\PageBuilderAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * The navigation on a reseller's own site — the counterpart to Admin\MenuController, scoped
 * to one tenant.
 *
 * Their site previously had no editable navigation at all. Menus were unique per location
 * across the whole platform, so the only ones that existed were ours, and the view composer
 * withheld those on a reseller host rather than advertise our About and Pricing on their
 * domain. The result was a white-labeled site whose header said "Home" and nothing else,
 * with no screen anywhere that could change it — including no way to link to the pages they
 * had just built in the page builder.
 *
 * Two things differ from the admin controller beyond the scoping:
 *
 *  - The route menu offers only destinations that mean something on their site. The
 *    platform's About, Pricing, Contact and directory are excluded by construction, not
 *    filtered afterwards: ResolveResellerByHost redirects those paths back to their front
 *    page anyway, so offering them would build a nav item that bounces the visitor.
 *  - Every lookup goes through resolveMenu()/ownedItem(), which bind on the tenant, so a
 *    guessed menu_location or item id cannot reach another reseller's navigation.
 */
class MenuController extends Controller
{
    /** Locations a reseller may build, same three the platform uses. */
    private const LOCATIONS = [
        Menu::LOCATION_HEADER,
        Menu::LOCATION_FOOTER_QUICK,
        Menu::LOCATION_FOOTER_COMPANY,
    ];

    /**
     * Gated on the same rule as the page builder itself: menus are the navigation *for*
     * those pages, so a tier that cannot create a page has nothing to point a menu at, and
     * splitting the two would let a reseller build a nav full of links that 404.
     */
    private function reseller(Request $request): Reseller
    {
        $reseller = $request->user()->reseller;

        abort_unless(PageBuilderAccess::allows($reseller), 403);

        return $reseller;
    }

    public function edit(Request $request): View
    {
        $reseller = $this->reseller($request);

        $menus = Menu::query()
            ->forTenant($reseller->id)
            ->with(['allItems' => fn ($q) => $q->whereNull('parent_id')->orderBy('sort_order')])
            ->get()
            ->keyBy('location');

        return view('pages.reseller.menus', [
            'title' => 'Navigation menus',
            'reseller' => $reseller,
            'menus' => $menus,
            'menuRouteGroups' => $this->menuRouteOptionGroups($reseller),
        ]);
    }

    /**
     * Destinations offered in the "link to" picker.
     *
     * Deliberately short. Everything here resolves correctly when the visitor is on the
     * reseller's own host, and nothing here is a page about the platform.
     *
     * @return array<string, array<string, string>>
     */
    private function menuRouteOptionGroups(Reseller $reseller): array
    {
        $siteLabels = [
            'home' => 'Home',
            'memorial.create.step1' => 'Create memorial',
            'login' => 'Sign in',
            'register' => 'Register',
            'dashboard' => 'Dashboard',
            'memorials.index' => 'My memorials',
        ];

        $site = [];
        foreach ($siteLabels as $name => $label) {
            if (Route::has($name)) {
                $site[$name] = $label.' · '.$this->safeRoutePath($name);
            }
        }

        // Their own pages only. getBySlugForReseller()'s counterpart for a full list — a
        // platform page in this menu would send their visitor to our site.
        $pages = [];
        foreach (Page::query()->where('reseller_id', $reseller->id)->orderBy('title')->get() as $page) {
            if ($page->slug === Page::SLUG_VISITOR_HOME) {
                continue; // Already offered as "Home" above.
            }
            $pages['cms.page::'.$page->slug] = $page->title.' · /'.$page->slug;
        }

        return array_filter([
            'Site' => $site,
            'Your pages' => $pages,
        ]);
    }

    private function safeRoutePath(string $routeName): string
    {
        try {
            return parse_url(route($routeName, [], false), PHP_URL_PATH) ?: '/';
        } catch (\Throwable) {
            return '/…';
        }
    }

    /**
     * @return array{0: ?string, 1: ?array<string, string>}
     */
    private function parseRouteSelection(?string $value, Reseller $reseller): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        if (str_starts_with($value, 'cms.page::')) {
            $slug = substr($value, strlen('cms.page::'));
            if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                return [null, null];
            }
            // Scoped: without the reseller_id the picker would accept any platform page slug
            // typed by hand and publish a link to our site from their navigation.
            if (! Page::query()->where('reseller_id', $reseller->id)->where('slug', $slug)->exists()) {
                return [null, null];
            }

            return ['cms.page', ['slug' => $slug]];
        }

        if (! array_key_exists($value, $this->menuRouteOptionGroups($reseller)['Site'] ?? [])) {
            return [null, null];
        }

        return [$value, null];
    }

    /**
     * @return \Closure(string, mixed, \Closure): void
     */
    private function routeSelectionRule(Reseller $reseller): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($reseller): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            [$routeName] = $this->parseRouteSelection($value, $reseller);

            if ($routeName === null) {
                $fail('Choose a destination from the list.');
            }
        };
    }

    /** The tenant's menu for a location, created on first use. */
    private function resolveMenu(Reseller $reseller, string $location): Menu
    {
        return Menu::query()->forTenant($reseller->id)->where('location', $location)->first()
            ?? Menu::query()->create(['reseller_id' => $reseller->id, 'location' => $location]);
    }

    /** An item that genuinely belongs to this tenant, or 404. */
    private function ownedItem(Reseller $reseller, MenuItem $item): MenuItem
    {
        abort_unless($item->menu?->reseller_id === $reseller->id, 404);

        return $item;
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $reseller = $this->reseller($request);

        $request->validate([
            'menu_location' => 'required|string|in:'.implode(',', self::LOCATIONS),
            'label' => 'required|string|max:120',
            'route_name' => ['nullable', 'string', 'max:200', $this->routeSelectionRule($reseller)],
            'url' => 'nullable|string|max:2048',
        ]);

        [$routeName, $routeParams] = $this->parseRouteSelection($request->input('route_name'), $reseller);

        if ($routeName === null && ! $request->filled('url')) {
            return back()->withErrors(['route_name' => 'Choose a destination or enter a custom URL.'])->withInput();
        }

        $menu = $this->resolveMenu($reseller, $request->input('menu_location'));
        $max = (int) $menu->allItems()->whereNull('parent_id')->max('sort_order');

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'label' => $request->input('label'),
            'route_name' => $routeName,
            'url' => $request->filled('url') ? $request->input('url') : null,
            'route_parameters' => $routeParams,
            'open_in_new_tab' => $request->boolean('open_in_new_tab'),
            'sort_order' => $max + 1,
        ]);

        return back()->with('success', 'Menu item added.');
    }

    public function updateItem(Request $request, MenuItem $item): RedirectResponse
    {
        $reseller = $this->reseller($request);
        $item = $this->ownedItem($reseller, $item);

        $request->validate([
            'label' => 'required|string|max:120',
            'route_name' => ['nullable', 'string', 'max:200', $this->routeSelectionRule($reseller)],
            'url' => 'nullable|string|max:2048',
        ]);

        [$routeName, $routeParams] = $this->parseRouteSelection($request->input('route_name'), $reseller);

        if ($routeName === null && ! $request->filled('url')) {
            return back()->withErrors(['route_name' => 'Choose a destination or enter a custom URL.'])->withInput();
        }

        $item->update([
            'label' => $request->input('label'),
            'route_name' => $routeName,
            'url' => $request->filled('url') ? $request->input('url') : null,
            'route_parameters' => $routeParams,
            'open_in_new_tab' => $request->boolean('open_in_new_tab'),
        ]);

        return back()->with('success', 'Menu item updated.');
    }

    public function destroyItem(Request $request, MenuItem $item): RedirectResponse
    {
        $this->ownedItem($this->reseller($request), $item)->delete();

        return back()->with('success', 'Menu item removed.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $reseller = $this->reseller($request);

        $request->validate([
            'menu_location' => 'required|string|in:'.implode(',', self::LOCATIONS),
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer',
        ]);

        $menu = Menu::query()->forTenant($reseller->id)->where('location', $request->input('menu_location'))->first();

        if (! $menu) {
            return back();
        }

        // The where('menu_id') is the guard: an id belonging to another tenant's menu simply
        // matches nothing rather than being reordered into this one.
        foreach ($request->input('item_ids', []) as $order => $id) {
            MenuItem::query()
                ->where('id', $id)
                ->where('menu_id', $menu->id)
                ->update(['sort_order' => (int) $order]);
        }

        return back()->with('success', 'Order saved.');
    }
}
