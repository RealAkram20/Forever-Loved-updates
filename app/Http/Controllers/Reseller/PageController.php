<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Memorial;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\PageBuilder\WidgetRegistry;
use App\Services\PageLayoutService;
use App\Support\PageBuilderAccess;
use App\Support\ResellerPageContext;
use App\Support\StandardPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The reseller's own website: the same drag-and-drop page builder the platform admin uses,
 * scoped to this tenant. Mirrors Admin\PageController deliberately — same widget pipeline,
 * same validation shape, same editor payload — so the two never drift. The differences are
 * all about tenancy:
 *
 *  - Every query is constrained to $reseller->id. A reseller can only ever see, edit or
 *    delete their own pages, and their slugs live in a namespace separate from the
 *    platform's and from every other reseller's.
 *  - The whole area is gated behind the tier's feature_page_builder flag, the same way
 *    Analytics is gated behind feature_business_analytics.
 *
 * Pages come in two kinds. Standard pages (StandardPages) are the ones every site has —
 * About, Pricing, Contact and so on — switched on and off rather than created and deleted;
 * a reseller used to get none of them, because Page::reservedSlugs() refused the slugs and
 * the host middleware redirected the paths. Custom pages are anything else they build.
 */
class PageController extends Controller
{
    private function reseller(Request $request): Reseller
    {
        return $request->user()->reseller()->with('tier')->firstOrFail();
    }

    /**
     * Paid capability, not forbidden data: an un-entitled reseller sees the pitch, not a 403.
     * A super-admin logged in as the reseller keeps access regardless of the tier — see
     * PageBuilderAccess.
     */
    private function ensureEntitled(Reseller $reseller): void
    {
        abort_unless(PageBuilderAccess::allows($reseller), 403);
    }

    public function index(Request $request): View
    {
        $reseller = $this->reseller($request);

        if (! PageBuilderAccess::allows($reseller)) {
            return view('pages.reseller.pages.index', [
                'title' => 'Pages',
                'reseller' => $reseller,
                'locked' => true,
                'pages' => collect(),
                'homePage' => null,
            ]);
        }

        $owned = Page::where('reseller_id', $reseller->id)->get()->keyBy('slug');

        return view('pages.reseller.pages.index', [
            'title' => 'Pages',
            'reseller' => $reseller,
            'locked' => false,
            // Custom pages only — standard ones are listed separately with a switch rather
            // than a delete button, since removing a site's About page is a different act
            // from turning it off.
            'pages' => $owned->reject(fn (Page $page) => StandardPages::isStandard($page->slug))
                ->sortBy('title')
                ->values(),
            // slug => ['definition' => ..., 'page' => ?Page]. A missing row simply reads as
            // off; the row is created on first enable.
            'standardPages' => collect(StandardPages::catalogue())
                ->map(fn (array $definition, string $slug) => [
                    'slug' => $slug,
                    'definition' => $definition,
                    'page' => $owned->get($slug),
                    'enabled' => (bool) $owned->get($slug)?->is_published,
                ])
                ->values(),
            'homePage' => $owned->get(Page::SLUG_VISITOR_HOME),
        ]);
    }

    /**
     * Switch a standard page on or off.
     *
     * On: create the row if this tenant has never had one — seeded from the platform's
     * equivalent where that makes sense, so a reseller's privacy policy starts as real text
     * rather than a blank page nobody remembers to write — then publish it.
     *
     * Off: unpublish. Never delete. A reseller who hides their pricing page for a month and
     * switches it back on should find their own copy, not an empty one.
     */
    public function toggleStandard(Request $request, string $slug): RedirectResponse
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        abort_unless(StandardPages::isStandard($slug), 404);

        $enable = $request->boolean('enabled');
        $definition = StandardPages::definition($slug);

        if (! $enable && ! StandardPages::isDisableable($slug)) {
            return back()->with('error', "“{$definition['title']}” cannot be switched off — every site needs one.");
        }

        $page = Page::where('reseller_id', $reseller->id)->where('slug', $slug)->first();

        if (! $page) {
            $page = Page::create([
                'reseller_id' => $reseller->id,
                'slug' => $slug,
                'title' => $definition['title'],
                // Seeded from ours so the page is publishable immediately. Legal text they
                // have not read yet is still better than a site with no policy at all, and
                // it is theirs to edit from the moment it exists.
                'content' => $definition['seed_from_platform'] ? Page::getBySlug($slug)?->content : null,
                'layout' => null,
                'is_published' => $enable,
            ]);
        } else {
            $page->update(['is_published' => $enable]);
        }

        Page::clearSlugCache($slug, $reseller->id);

        return back()->with('success', $enable
            ? "“{$page->title}” is now live on your site."
            : "“{$page->title}” is hidden. Your content is kept.");
    }

    /**
     * The reseller's own homepage — the front page served at the root of their site. A
     * system page (fixed slug, never deletable) created on first edit; until it has widgets,
     * their front page keeps rendering the shared branded home layout.
     */
    public function editHome(Request $request): View
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        $page = Page::where('reseller_id', $reseller->id)
            ->where('slug', Page::SLUG_VISITOR_HOME)
            ->first();

        // First open — or an empty home that has never been built — is seeded from the
        // platform's default home layout, so the reseller edits their *actual current*
        // homepage (the branded default they already serve) instead of a blank canvas.
        // An empty home renders that same default anyway, so seeding it changes nothing a
        // visitor sees; it just makes it editable.
        if (! $page || ! $page->hasLayout()) {
            $starter = $this->starterHomeLayout();

            if ($page) {
                $page->update(['layout' => $starter]);
            } else {
                $page = Page::create([
                    'reseller_id' => $reseller->id,
                    'slug' => Page::SLUG_VISITOR_HOME,
                    'title' => 'Home',
                    'layout' => $starter,
                    'is_published' => true,
                ]);
            }

            Page::clearSlugCache(Page::SLUG_VISITOR_HOME, $reseller->id);
        }

        return view('pages.reseller.pages.layout-editor', [
            'title' => 'Edit homepage',
            'layoutHeading' => 'Homepage',
            'isCreateMode' => false,
            'reseller' => $reseller,
            'page' => $page,
            'widgetDefinitions' => app(WidgetRegistry::class)->definitionsForEditor(true),
            'initialDocument' => app(PageLayoutService::class)->initialDocumentForEditor($page),
        ]);
    }

    /**
     * A copy of the platform's default home layout, with fresh widget ids so the reseller's
     * page never aliases the platform's. If the platform home is not itself widget-based
     * (a legacy blade layout with no widgets), the reseller simply starts empty.
     *
     * @return array{version: int, widgets: array<int, mixed>}
     */
    private function starterHomeLayout(): array
    {
        $platform = Page::getBySlug(Page::SLUG_VISITOR_HOME);
        $widgets = is_array($platform?->layout['widgets'] ?? null) ? $platform->layout['widgets'] : [];

        $widgets = array_map(function ($widget) {
            if (is_array($widget)) {
                $widget['id'] = 'w_'.substr(bin2hex(random_bytes(6)), 0, 12);
            }

            return $widget;
        }, $widgets);

        return ['version' => 1, 'widgets' => array_values($widgets)];
    }

    public function create(Request $request): View
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        return view('pages.reseller.pages.layout-editor', [
            'title' => 'Add page',
            'layoutHeading' => 'Add page',
            'isCreateMode' => true,
            'reseller' => $reseller,
            'page' => null,
            'widgetDefinitions' => app(WidgetRegistry::class)->definitionsForEditor(true),
            'initialDocument' => ['version' => 1, 'widgets' => []],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        if (! ($request->expectsJson() || $request->wantsJson())) {
            abort(405);
        }

        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->where(fn ($q) => $q->where('reseller_id', $reseller->id)),
                Rule::notIn(Page::reservedSlugs()),
                $this->notAMemorialSlug($reseller),
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
            'reseller_id' => $reseller->id,
            'slug' => $validated['slug'],
            'title' => $validated['title'],
            'meta_title' => ($validated['meta_title'] ?? null) ?: null,
            'content' => null,
            'layout' => $normalized,
            'meta_description' => $validated['meta_description'] ?? null,
            'is_published' => $request->boolean('is_published', true),
            'og_image' => null,
        ]);

        Page::clearSlugCache($validated['slug'], $reseller->id);

        return response()->json([
            'success' => true,
            'message' => 'Page created.',
            'redirect' => route('reseller.pages.edit', $validated['slug']),
        ]);
    }

    public function edit(Request $request, string $slug): View
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        $page = $this->findPage($reseller, $slug);

        return view('pages.reseller.pages.layout-editor', [
            'title' => "Edit {$page->title}",
            'layoutHeading' => $page->title,
            'isCreateMode' => false,
            'reseller' => $reseller,
            'page' => $page,
            'widgetDefinitions' => app(WidgetRegistry::class)->definitionsForEditor(true),
            'initialDocument' => app(PageLayoutService::class)->initialDocumentForEditor($page),
        ]);
    }

    public function updatePageMeta(Request $request, string $slug): JsonResponse
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        $page = $this->findPage($reseller, $slug);
        $oldSlug = $page->slug;

        // The homepage has a fixed slug (it is the site root, not /visitor-home); only custom
        // pages can be re-slugged.
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
                Rule::unique('pages', 'slug')->where(fn ($q) => $q->where('reseller_id', $reseller->id))->ignore($page->id),
                Rule::notIn(Page::reservedSlugs()),
                $this->notAMemorialSlug($reseller),
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
            $this->deleteOgImage($page);
            $data['og_image'] = null;
        } elseif ($request->hasFile('og_image')) {
            $this->deleteOgImage($page);
            $data['og_image'] = $request->file('og_image')->store('seo', 'public');
        }

        $page->update($data);
        $page->refresh();

        Page::clearSlugCache($oldSlug, $reseller->id);
        if ($page->slug !== $oldSlug) {
            Page::clearSlugCache($page->slug, $reseller->id);
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
            'redirect' => $slugChanged ? route('reseller.pages.edit', $page->slug) : null,
        ]);
    }

    public function updateLayout(Request $request, string $slug): JsonResponse
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        $page = $this->findPage($reseller, $slug);

        try {
            $normalized = app(PageLayoutService::class)->validateDocumentFromArray($request->all());
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $page->layout = $normalized;
        if ($normalized['widgets'] !== []) {
            $page->content = null;
        }
        $page->save();
        Page::clearSlugCache($slug, $reseller->id);

        return response()->json(['success' => true, 'message' => 'Layout saved.']);
    }

    /**
     * Live editor preview: the posted (unsaved) document rendered through the same widget
     * pipeline the public page uses, with this reseller's own plans and memorials as context
     * so a Pricing or Showcase widget shows their data, never the platform's.
     */
    public function preview(Request $request): \Illuminate\Http\Response
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        $document = app(PageLayoutService::class)->normalizeDocumentLenient([
            'version' => $request->input('version', 1),
            'widgets' => $request->input('widgets', []),
        ]);

        // Render the preview in the reseller's own template.
        //
        // This endpoint is on *our* host, so none of the ResolveReseller* middleware ran and
        // ActiveTheme was still on the base template. The harness extends layouts.visitor,
        // which a template overrides — so a reseller running Dignified was shown their page in
        // the platform's design: sans-serif where their site is small-caps serif, a rounded
        // crimson button where theirs is a square gold one, no gold rule, and `{theme}/…`
        // images resolving to a directory the base template does not have. They were editing
        // one design and publishing another, which is the one thing a live preview must not do.
        //
        // Applied here rather than in middleware because this is the only route that renders a
        // tenant's site from our host on purpose. templateSlug() already falls back to the base
        // template when their theme's directory is not deployed.
        app(\App\Themes\ActiveTheme::class)->use($reseller->templateSlug());

        // The preview view is a generic render harness (widgets + context, editable chrome);
        // it carries no platform-specific data of its own, so the reseller reuses it directly.
        return response()->view('pages.settings.pages.preview', [
            'title' => 'Preview',
            'widgets' => $document['widgets'],
            'layoutContext' => ResellerPageContext::forWidgets($reseller, array_column($document['widgets'], 'type')),
        ]);
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $reseller = $this->reseller($request);
        $this->ensureEntitled($reseller);

        $page = $this->findPage($reseller, $slug);

        // Standard pages are switched off, not deleted. Deleting would throw away content the
        // reseller may want back the moment they re-enable, and for the homepage and the legal
        // pages there is no "off" at all.
        if (StandardPages::isStandard($page->slug)) {
            $verb = StandardPages::isDisableable($page->slug)
                ? 'Switch it off instead — your content is kept.'
                : 'Every site needs one.';

            return redirect()
                ->route('reseller.pages.index')
                ->with('error', "“{$page->title}” cannot be deleted. {$verb}");
        }

        $this->deleteOgImage($page);

        $title = $page->title;
        Page::clearSlugCache($slug, $reseller->id);
        $page->delete();

        return redirect()
            ->route('reseller.pages.index')
            ->with('success', "Deleted “{$title}”.");
    }

    private function findPage(Reseller $reseller, string $slug): Page
    {
        return Page::where('reseller_id', $reseller->id)->where('slug', $slug)->firstOrFail();
    }

    private function deleteOgImage(Page $page): void
    {
        if (is_string($page->og_image) && $page->og_image !== '' && Storage::disk('public')->exists($page->og_image)) {
            Storage::disk('public')->delete($page->og_image);
        }
    }

    /**
     * A page slug must not collide with one of this reseller's memorial slugs: both are
     * served at {host}/{slug}, and a clash would make one shadow the other.
     */
    private function notAMemorialSlug(Reseller $reseller): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($reseller) {
            if (Memorial::where('reseller_id', $reseller->id)->where('slug', $value)->exists()) {
                $fail('That address is already used by one of your memorials. Pick a different slug.');
            }
        };
    }
}
