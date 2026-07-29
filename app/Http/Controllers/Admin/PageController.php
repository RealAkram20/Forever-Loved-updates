<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\SeoEntry;
use App\Models\SiteLayout;
use App\PageBuilder\WidgetRegistry;
use App\Services\PageLayoutService;
use App\Services\SeoMetaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PageController extends Controller
{
    public function index()
    {
        $this->ensureMarketingSeoRows();

        $pages = Page::whereNotIn('slug', Page::systemLayoutSlugs())
            ->orderBy('title')
            ->get();

        $homePage = Page::query()->where('slug', Page::SLUG_VISITOR_HOME)->first();
        $homeLayout = SiteLayout::query()->where('key', SiteLayout::KEY_VISITOR_HOME)->first();
        $seoHome = SeoEntry::query()->where('route_key', 'home')->first();
        $seoPricing = SeoEntry::query()->where('route_key', 'pricing')->first();
        $pricingPage = Page::query()->where('slug', 'pricing')->first();
        $contactPage = Page::query()->where('slug', 'contact')->first();
        $findMemorialPage = Page::query()->where('slug', Page::SLUG_FIND_MEMORIAL)->first();
        $seoFindMemorial = SeoEntry::query()->where('route_key', 'memorial.directory')->first();
        $seoContact = SeoEntry::query()->where('route_key', 'contact')->first();

        $homeTimestamps = array_filter([$homePage?->updated_at, $homeLayout?->updated_at, $seoHome?->updated_at]);
        $homeUpdatedAt = $homeTimestamps !== [] ? max($homeTimestamps) : null;

        $systemPages = [
            [
                'title' => 'Home',
                'subtitle' => $homePage ? 'Homepage layout and metadata' : 'Legacy homepage JSON (seed a visitor-home page to use the main editor)',
                'path' => parse_url(route('home'), PHP_URL_PATH) ?: '/',
                'is_published' => true,
                'updated_at' => $homeUpdatedAt,
                'view_url' => route('home'),
                'edit_url' => $homePage
                    ? route('settings.pages.edit', Page::SLUG_VISITOR_HOME)
                    : route('settings.site-layout.edit', SiteLayout::KEY_VISITOR_HOME),
                'edit_label' => $homePage ? 'Edit page' : 'Edit layout',
                'extra_links' => [],
            ],
            [
                'title' => 'Pricing',
                'subtitle' => 'Plans page · layout and metadata',
                'path' => parse_url(route('pricing'), PHP_URL_PATH) ?: '/pricing',
                'is_published' => true,
                'updated_at' => $pricingPage?->updated_at ?? $seoPricing?->updated_at,
                'view_url' => route('pricing'),
                'edit_url' => $pricingPage
                    ? route('settings.pages.edit', 'pricing')
                    : route('settings.pages.seo.edit', 'pricing'),
                'edit_label' => $pricingPage ? 'Edit page' : 'Edit details',
                'extra_links' => [],
            ],
            [
                'title' => 'Find Memorial',
                'subtitle' => $findMemorialPage ? 'Directory layout and metadata' : 'Memorial directory · social preview',
                'path' => parse_url(route('memorial.directory'), PHP_URL_PATH) ?: '/find-memorial',
                'is_published' => true,
                'updated_at' => $findMemorialPage?->updated_at ?? $seoFindMemorial?->updated_at,
                'view_url' => route('memorial.directory'),
                'edit_url' => $findMemorialPage
                    ? route('settings.pages.edit', Page::SLUG_FIND_MEMORIAL)
                    : route('settings.pages.seo.edit', 'memorial.directory'),
                'edit_label' => $findMemorialPage ? 'Edit page' : 'Edit details',
                'extra_links' => [],
            ],
            [
                'title' => 'Contact',
                'subtitle' => 'Contact page · layout and metadata',
                'path' => parse_url(route('contact'), PHP_URL_PATH) ?: '/contact',
                'is_published' => true,
                'updated_at' => $contactPage?->updated_at ?? $seoContact?->updated_at,
                'view_url' => route('contact'),
                'edit_url' => $contactPage
                    ? route('settings.pages.edit', 'contact')
                    : route('settings.pages.seo.edit', 'contact'),
                'edit_label' => $contactPage ? 'Edit page' : 'Edit details',
                'extra_links' => [],
            ],
        ];

        return view('pages.settings.pages.index', [
            'title' => 'Manage Pages',
            'systemPages' => $systemPages,
            'pages' => $pages,
        ]);
    }

    public function create(): View
    {
        return view('pages.settings.pages.layout-editor', [
            'title' => 'Add page',
            'layoutHeading' => 'Add page',
            'isCreateMode' => true,
            'page' => null,
            'widgetDefinitions' => app(WidgetRegistry::class)->definitionsForEditor(),
            'initialDocument' => ['version' => 1, 'widgets' => []],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return $this->storeFromBuilderJson($request);
        }

        abort(405);
    }

    public function edit(string $slug): View
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return $this->editorView($page);
    }

    public function editLayout(string $slug): RedirectResponse
    {
        return redirect()->route('settings.pages.edit', $slug);
    }

    public function updatePageMeta(Request $request, string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $oldSlug = $page->slug;

        $rules = [
            'title' => 'required|string|max:255',
            'meta_title' => 'nullable|string|max:120',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|image|max:4096',
            'remove_og_image' => 'nullable|boolean',
            'is_published' => 'boolean',
        ];

        if (! $page->isSystemLayoutPage()) {
            $rules['slug'] = [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->ignore($page->id),
                Rule::notIn(Page::reservedSlugs()),
            ];
        }

        $request->validate($rules);

        $data = [
            'title' => $request->input('title'),
            'meta_title' => $request->input('meta_title') ?: null,
            'meta_description' => $request->input('meta_description'),
            'is_published' => $request->boolean('is_published', true),
        ];

        if (! $page->isSystemLayoutPage()) {
            $newSlug = strtolower(trim((string) $request->input('slug')));
            if ($newSlug !== $page->slug) {
                $data['slug'] = $newSlug;
            }
        }

        if ($request->boolean('remove_og_image')) {
            if (is_string($page->og_image) && $page->og_image !== '' && Storage::disk('public')->exists($page->og_image)) {
                Storage::disk('public')->delete($page->og_image);
            }
            $data['og_image'] = null;
        } elseif ($request->hasFile('og_image')) {
            if (is_string($page->og_image) && $page->og_image !== '' && Storage::disk('public')->exists($page->og_image)) {
                Storage::disk('public')->delete($page->og_image);
            }
            $data['og_image'] = $request->file('og_image')->store('seo', 'public');
        }

        $page->update($data);
        $page->refresh();

        Page::clearSlugCache($oldSlug);
        if ($page->slug !== $oldSlug) {
            Page::clearSlugCache($page->slug);
        }

        $ogUrl = null;
        if (is_string($page->og_image) && $page->og_image !== '') {
            $ogUrl = Storage::disk('public')->url($page->og_image);
        }

        $slugChanged = $page->slug !== $oldSlug;

        return response()->json([
            'success' => true,
            'message' => 'Page details saved.',
            'title' => $page->title,
            'og_image_url' => $ogUrl,
            'new_slug' => $page->slug,
            'slug_changed' => $slugChanged,
            'redirect' => $slugChanged ? route('settings.pages.edit', $page->slug) : null,
        ]);
    }

    public function updateLayout(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        try {
            $normalized = app(PageLayoutService::class)->validateDocumentFromArray($request->all());
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['layout' => $e->getMessage()])->withInput();
        }

        $page->layout = $normalized;
        if ($normalized['widgets'] !== []) {
            $page->content = null;
        }
        $page->save();
        Page::clearSlugCache($slug);

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Layout saved.']);
        }

        return redirect()
            ->route('settings.pages.edit', $slug)
            ->with('success', 'Layout saved.');
    }

    /**
     * Live editor preview: renders the posted (unsaved) layout document with the
     * same widget pipeline and context the public pages use. Lenient — a
     * half-typed document must still render, so no validation errors here.
     */
    public function preview(Request $request): \Illuminate\Http\Response
    {
        $document = app(PageLayoutService::class)->normalizeDocumentLenient([
            'version' => $request->input('version', 1),
            'widgets' => $request->input('widgets', []),
        ]);

        $types = array_column($document['widgets'], 'type');

        $context = [
            'tagline' => \App\Models\SystemSetting::get('branding.tagline', 'Celebrate lives that matter'),
        ];

        if (in_array(\App\PageBuilder\Widgets\PricingPlansWidget::type(), $types, true)) {
            $context['plans'] = \App\Models\SubscriptionPlan::where('is_active', true)
                ->sellableTo(auth()->user())
                ->orderBy('sort_order')
                ->get();
            $context['currency'] = \App\Models\SystemSetting::get('payments.currency', 'USD');
        }

        if (in_array(\App\PageBuilder\Widgets\MemorialShowcaseWidget::type(), $types, true)) {
            $context['popularMemorials'] = \App\Models\Memorial::where('is_public', true)
                ->where('status', \App\Models\Memorial::STATUS_ACTIVE)
                ->whereNotNull('first_name')
                ->whereNotNull('last_name')
                ->withCount(['views as view_count', 'tributes as tribute_count'])
                ->orderByDesc('view_count')
                ->limit(12)
                ->get()
                ->filter(fn ($m) => $m->completion_percentage >= 40)
                ->take(8);
        }

        return response()->view('pages.settings.pages.preview', [
            'title' => 'Preview',
            'widgets' => $document['widgets'],
            'layoutContext' => $context,
        ]);
    }

    public function destroy(string $slug): RedirectResponse
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        if ($page->isSystemLayoutPage()) {
            return redirect()
                ->route('settings.pages.index')
                ->with('error', 'This system page cannot be deleted.');
        }

        if (is_string($page->og_image) && $page->og_image !== '' && Storage::disk('public')->exists($page->og_image)) {
            Storage::disk('public')->delete($page->og_image);
        }

        $title = $page->title;
        Page::clearSlugCache($slug);
        $page->delete();

        return redirect()
            ->route('settings.pages.index')
            ->with('success', "Deleted “{$title}”.");
    }

    public function editSeoRoute(string $routeKey)
    {
        if (! in_array($routeKey, SeoMetaResolver::ROUTES_WITH_OVERRIDES, true)) {
            abort(404);
        }

        $cmsSlug = SeoMetaResolver::pageSlugForMarketingRoute($routeKey);
        if ($cmsSlug !== null) {
            return redirect()->route('settings.pages.edit', $cmsSlug);
        }

        $this->ensureMarketingSeoRows();
        $entry = SeoEntry::query()->firstOrCreate(
            ['route_key' => $routeKey],
            ['label' => SeoEntry::routeLabels()[$routeKey] ?? $routeKey]
        );

        return view('pages.settings.pages.seo-route', [
            'title' => 'SEO: '.$entry->label,
            'entry' => $entry,
        ]);
    }

    public function updateSeoRoute(Request $request, string $routeKey): RedirectResponse
    {
        if (! in_array($routeKey, SeoMetaResolver::ROUTES_WITH_OVERRIDES, true)) {
            abort(404);
        }

        $cmsSlug = SeoMetaResolver::pageSlugForMarketingRoute($routeKey);
        if ($cmsSlug !== null) {
            return redirect()->route('settings.pages.edit', $cmsSlug);
        }

        $entry = SeoEntry::query()->where('route_key', $routeKey)->firstOrFail();

        $request->validate([
            'meta_title' => 'nullable|string|max:120',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|image|max:4096',
            'remove_og_image' => 'nullable|boolean',
        ]);

        $data = [
            'meta_title' => $request->input('meta_title') ?: null,
            'meta_description' => $request->input('meta_description') ?: null,
        ];

        if ($request->boolean('remove_og_image')) {
            if (is_string($entry->og_image) && $entry->og_image !== '' && Storage::disk('public')->exists($entry->og_image)) {
                Storage::disk('public')->delete($entry->og_image);
            }
            $data['og_image'] = null;
        } elseif ($request->hasFile('og_image')) {
            if (is_string($entry->og_image) && $entry->og_image !== '' && Storage::disk('public')->exists($entry->og_image)) {
                Storage::disk('public')->delete($entry->og_image);
            }
            $data['og_image'] = $request->file('og_image')->store('seo', 'public');
        }

        $entry->update($data);

        return redirect()
            ->route('settings.pages.index')
            ->with('success', 'SEO settings saved.');
    }

    private function editorView(Page $page): View
    {
        $initialDocument = app(PageLayoutService::class)->initialDocumentForEditor($page);

        return view('pages.settings.pages.layout-editor', [
            'title' => "Edit {$page->title}",
            'layoutHeading' => $page->title,
            'isCreateMode' => false,
            'page' => $page,
            'widgetDefinitions' => app(WidgetRegistry::class)->definitionsForEditor(),
            'initialDocument' => $initialDocument,
        ]);
    }

    private function storeFromBuilderJson(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:pages,slug',
                Rule::notIn(Page::reservedSlugs()),
            ],
            'title' => 'required|string|max:255',
            'meta_title' => 'nullable|string|max:120',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'layout' => 'required|array',
            'layout.version' => 'sometimes|integer|min:1',
            'layout.widgets' => 'present|array',
        ]);

        $layoutInput = $request->input('layout', []);
        $document = [
            'version' => (int) ($layoutInput['version'] ?? 1),
            'widgets' => is_array($layoutInput['widgets'] ?? null) ? $layoutInput['widgets'] : [],
        ];

        try {
            $normalized = app(PageLayoutService::class)->validateDocumentFromArray($document);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Page::query()->create([
            'slug' => $validated['slug'],
            'title' => $validated['title'],
            'meta_title' => $validated['meta_title'] ?: null,
            'content' => null,
            'layout' => $normalized,
            'meta_description' => $validated['meta_description'],
            'is_published' => $request->boolean('is_published', true),
            'og_image' => null,
        ]);

        Page::clearSlugCache($validated['slug']);

        return response()->json([
            'success' => true,
            'message' => 'Page created.',
            'redirect' => route('settings.pages.edit', $validated['slug']),
        ]);
    }

    private function ensureMarketingSeoRows(): void
    {
        $labels = SeoEntry::routeLabels();
        foreach (SeoMetaResolver::ROUTES_WITH_OVERRIDES as $key) {
            SeoEntry::query()->firstOrCreate(
                ['route_key' => $key],
                ['label' => $labels[$key] ?? $key]
            );
        }
    }
}
