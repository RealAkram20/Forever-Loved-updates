<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function edit(): View
    {
        $menus = Menu::query()->with(['allItems' => fn ($q) => $q->whereNull('parent_id')->orderBy('sort_order')])->get()->keyBy('location');

        return view('pages.settings.menus.edit', [
            'title' => 'Navigation menus',
            'menus' => $menus,
            'menuRouteGroups' => $this->menuRouteOptionGroups(),
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function menuRouteOptionGroups(): array
    {
        $siteLabels = [
            'home' => 'Home',
            'pricing' => 'Pricing',
            'contact' => 'Contact',
            'about' => 'About',
            'privacy-policy' => 'Privacy Policy',
            'terms-of-use' => 'Terms of Use',
            'memorial.directory' => 'Find memorial',
            'memorial.create.step1' => 'Create memorial',
            'login' => 'Login',
            'register' => 'Register',
            'dashboard' => 'Dashboard',
            'calendar' => 'Calendar',
            'subscription.index' => 'My subscription',
            'notifications.index' => 'Notifications',
            'profile.edit' => 'Profile',
            'memorials.index' => 'My memorials',
            'memorials.create' => 'New memorial',
        ];

        $site = [];
        foreach ($siteLabels as $name => $label) {
            if (Route::has($name)) {
                $site[$name] = $label.' · '.$this->safeRoutePath($name);
            }
        }

        foreach (Page::query()->whereIn('slug', ['about', 'privacy-policy', 'terms-of-use'])->get() as $page) {
            $routeName = $page->slug === 'about' ? 'about' : $page->slug;
            if (isset($site[$routeName])) {
                $site[$routeName] = $page->title.' · '.$this->safeRoutePath($routeName);
            }
        }

        $cmsPages = [];
        foreach (Page::query()->orderBy('title')->get() as $page) {
            if (in_array($page->slug, ['about', 'privacy-policy', 'terms-of-use'], true)) {
                continue;
            }
            $path = parse_url($page->publicUrl(), PHP_URL_PATH) ?: '/'.$page->slug;
            $cmsPages['cms.page::'.$page->slug] = $page->title.' · '.$path;
        }

        return [
            'Site routes' => $site,
            'CMS pages' => $cmsPages,
        ];
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
    private function parseRouteSelection(?string $value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }
        if (str_starts_with($value, 'cms.page::')) {
            $slug = substr($value, strlen('cms.page::'));
            if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                return [null, null];
            }
            if (! Page::query()->where('slug', $slug)->exists()) {
                return [null, null];
            }

            return ['cms.page', ['slug' => $slug]];
        }
        if (! Route::has($value)) {
            return [null, null];
        }

        return [$value, null];
    }

    /**
     * @return \Closure(string, mixed, \Closure): void
     */
    private function routeSelectionRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }
            if (str_starts_with($value, 'cms.page::')) {
                $slug = substr($value, strlen('cms.page::'));
                if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                    $fail('Invalid page slug.');

                    return;
                }
                if (! Page::query()->where('slug', $slug)->exists()) {
                    $fail('That CMS page does not exist.');

                    return;
                }

                return;
            }
            if (! Route::has($value)) {
                $fail('Unknown route.');
            }
        };
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $request->validate([
            'menu_location' => 'required|string|in:'.implode(',', [
                Menu::LOCATION_HEADER,
                Menu::LOCATION_FOOTER_QUICK,
                Menu::LOCATION_FOOTER_COMPANY,
            ]),
            'label' => 'required|string|max:120',
            'route_name' => ['nullable', 'string', 'max:200', $this->routeSelectionRule()],
            'url' => 'nullable|string|max:2048',
        ]);

        [$routeName, $routeParams] = $this->parseRouteSelection($request->input('route_name'));
        if ($routeName === null && ! $request->filled('url')) {
            return back()->withErrors(['route_name' => 'Choose a route or enter a custom URL.'])->withInput();
        }

        $menu = Menu::query()->where('location', $request->input('menu_location'))->firstOrFail();
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
        $request->validate([
            'label' => 'required|string|max:120',
            'route_name' => ['nullable', 'string', 'max:200', $this->routeSelectionRule()],
            'url' => 'nullable|string|max:2048',
        ]);

        [$routeName, $routeParams] = $this->parseRouteSelection($request->input('route_name'));
        if ($routeName === null && ! $request->filled('url')) {
            return back()->withErrors(['route_name' => 'Choose a route or enter a custom URL.'])->withInput();
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

    public function destroyItem(MenuItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('success', 'Menu item removed.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $request->validate([
            'menu_location' => 'required|string',
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer|exists:menu_items,id',
        ]);

        $menu = Menu::query()->where('location', $request->input('menu_location'))->firstOrFail();

        foreach ($request->input('item_ids', []) as $order => $id) {
            MenuItem::query()
                ->where('id', $id)
                ->where('menu_id', $menu->id)
                ->update(['sort_order' => (int) $order]);
        }

        return back()->with('success', 'Order saved.');
    }
}
