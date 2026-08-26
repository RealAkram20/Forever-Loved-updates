<?php

namespace App\Http\Controllers;

use App\Helpers\MemorialShareMetaHelper;
use App\Helpers\MemorialStatsHelper;
use App\Helpers\PlanLimitsHelper;
use App\Models\Memorial;
use App\Models\MemorialSubscription;
use App\Models\MemorialView;
use App\Models\Page;
use App\Models\PaymentOrder;
use App\Models\Reseller;
use App\Models\Tribute;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicMemorialController extends Controller
{
    /**
     * Stop a visitor's update emails for one memorial, from a link in the email itself.
     *
     * The subscribe/unsubscribe pair already on this site is a JSON endpoint that wants the
     * visitor to be back on the memorial page with the address they used typed in again —
     * which is not a thing anyone does from an inbox, so in practice these emails could not
     * be stopped at all.
     *
     * Deliberately idempotent, and deliberately quiet about what it did not find: clicking
     * twice, or clicking a link for a subscription somebody already removed, says
     * "unsubscribed" either way. The alternative — "no such subscription" — turns the page
     * into a check for whether a given address is subscribed to a given memorial, which is
     * not something a stranger with a copied link should be able to ask.
     */
    public function unsubscribe(Request $request, int $subscription)
    {
        $sub = MemorialSubscription::find($subscription);
        $memorial = $sub ? Memorial::find($sub->memorial_id) : null;

        if ($sub) {
            $sub->delete();
        }

        return view('pages.memorials.unsubscribed', [
            'title' => 'Unsubscribed',
            'memorial' => $memorial,
        ]);
    }

    /**
     * Display a public memorial by slug (no auth required).
     * CMS pages are checked first so custom pages live at /{slug} without the /p/ prefix.
     */
    public function show(string $slug)
    {
        $cmsPage = Page::getBySlug($slug);
        if ($cmsPage && $cmsPage->is_published && ! $cmsPage->isSystemLayoutPage()) {
            return app(PageController::class)->showPage($slug);
        }

        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        return $this->renderMemorial($memorial);
    }

    /**
     * Display a memorial on its reseller's white-labeled subdomain, or on their own
     * verified custom domain (same action either way). Scoped strictly to that
     * reseller's own memorials — without the reseller_id constraint, any reseller
     * subdomain could serve any platform memorial by slug, defeating white-labeling.
     * No CMS-page fallback here: these are memorial-pages-only in Phase 1.
     *
     * $reseller is unused — the actual reseller comes from the container binding set by
     * ResolveReseller/ResolveResellerByCustomDomain. It MUST stay the first parameter
     * though: Laravel splices route parameters into controller args positionally (by
     * route-parameter order — domain-level params first, then URI params — not by name),
     * so this needs to occupy the same slot the domain-level placeholder always fills for
     * both routes that call this action ({reseller} on the subdomain route, {domain} on
     * the custom-domain route), whatever that value happens to be, with $slug second.
     *
     * A memorial wins a slug over a CMS page of the same name: memorials are the product,
     * they far outnumber pages, and the reseller page builder already refuses to save a
     * slug that clashes with one of their memorials. If no memorial matches, a published
     * page of theirs with that slug is served (their About page, a landing page, …).
     */
    public function showForReseller(string $reseller, string $slug)
    {
        $resolvedReseller = app(Reseller::class);

        $memorial = Memorial::where('slug', $slug)->where('reseller_id', $resolvedReseller->id)->first();
        if ($memorial) {
            return $this->renderMemorial($memorial);
        }

        $page = Page::getBySlugForReseller($slug, $resolvedReseller->id);
        if ($page && $page->is_published) {
            return $this->renderResellerPage($resolvedReseller, $page);
        }

        abort(404);
    }

    /**
     * A reseller's own CMS page, on their host — the tenant-scoped equivalent of
     * PageController::showPage(). Rendered through the same widget pipeline the platform
     * pages use, with this reseller's plans and memorials as context so a Pricing or
     * Showcase widget shows their data, never ours.
     */
    private function renderResellerPage(Reseller $reseller, Page $page)
    {
        $widgets = is_array($page->layout['widgets'] ?? null) ? $page->layout['widgets'] : [];

        return view('pages.visitor.page-layout', [
            'title' => $page->title,
            'widgets' => $widgets,
            'layoutContext' => \App\Support\ResellerPageContext::forWidgets(
                $reseller,
                array_column($widgets, 'type')
            ),
            'shareMeta' => \App\Helpers\SiteShareMetaHelper::forCmsPageDirect(
                $page,
                $reseller->publicUrlForSlug($page->slug)
            ),
        ]);
    }

    /**
     * The reseller's own front page — the destination for the base address every reseller
     * screen shows and offers to copy. Until this existed that address had no route at all: it
     * 404'd on the path fallback, and on a real subdomain fell through to the *platform's*
     * homepage, complete with our logo, our copy and our pricing.
     *
     * Deliberately the same page as the platform home rather than a reduced listing of its own.
     * A reseller's front page should be the full designed home page wearing their brand, and
     * PageController::home() already resolves logo, palette, fonts, name and memorials through
     * the active tenant — so there is nothing here to duplicate, and no second design to keep
     * in step with the first.
     *
     * $reseller is unused for the same positional-argument reason as showForReseller() above;
     * the tenant comes from the container binding, which the route middleware has already set.
     */
    public function indexForReseller(?string $reseller = null)
    {
        // Fail loudly rather than silently rendering the platform home page if the middleware
        // ever stops binding — that regression is the whole bug this route exists to fix.
        app(Reseller::class);

        return app(PageController::class)->home();
    }

    /**
     * The memorial page.
     *
     * @param  \App\Models\Post|null  $highlight  A story reached by its own share link; the
     *                                            page opens on the feed and scrolls to it.
     */
    private function renderMemorial(Memorial $memorial, $highlight = null)
    {
        // Allow owner to view their own memorial even if private
        if (! $memorial->is_public && $memorial->user_id !== auth()->id()) {
            abort(404);
        }

        // Deactivated/suspended memorials are hidden from public (admin can still view via dashboard)
        if (in_array($memorial->status ?? 'active', ['deactivated', 'suspended']) && $memorial->user_id !== auth()->id() && ! auth()?->user()?->hasRole(['admin', 'super-admin'])) {
            abort(404);
        }

        if ($memorial->expires_at?->isPast()) {
            abort(404);
        }

        if ($memorial->is_public) {
            $this->recordView($memorial, request());
        }

        $this->loadMemorialRelations($memorial);

        // One feed. Everything anyone has written is here — a plain story, or one marked
        // as a flower, a candle or a prayer. Sorted once, in the controller, because three
        // places on the page read it and each was sorting its own copy.
        $stories = $memorial->posts->where('is_published', true)->sortByDesc('created_at')->values();

        $pendingPaymentOrder = null;
        if (auth()->id() && auth()->id() === $memorial->user_id) {
            $pendingPaymentOrder = PaymentOrder::where('memorial_id', $memorial->id)
                ->where('user_id', auth()->id())
                ->where('status', 'pending')
                ->latest()
                ->first();
        }

        return view('pages.memorials.public', [
            'title' => $memorial->full_name,
            'memorial' => $memorial,
            'stories' => $stories,
            'storyCounts' => $this->storyMarkerCounts($stories),
            'canEdit' => $memorial->canBeEditedBy(auth()->user()),
            'isAuthenticated' => auth()->check(),
            'memorialStats' => MemorialStatsHelper::get($memorial),
            'tributeCounts' => $this->getTributeTypeCounts($memorial),
            'quotaInfo' => PlanLimitsHelper::getQuotaInfo($memorial),
            'scrollToChapterId' => $highlight?->id,
            'shareMeta' => $highlight
                ? MemorialShareMetaHelper::forChapter($memorial, $highlight)
                : MemorialShareMetaHelper::forMemorial($memorial),
            'pendingPaymentOrder' => $pendingPaymentOrder,
        ]);
    }

    /**
     * A tribute share link, from back when a tribute could carry words.
     *
     * Those words are stories now, and they kept their share id, so the link somebody sent
     * two years ago still lands on the same writing — it just lands on it as a story. A
     * permanent redirect rather than a second render: there is one feed, and this address
     * has one correct destination.
     *
     * @see database/migrations/2026_08_08_100001_move_written_tributes_into_stories.php
     */
    public function showTribute(string $memorial_slug, string $share_id)
    {
        $memorial = $this->tenantScopedMemorial($memorial_slug);

        $post = $memorial->posts()->where('share_id', $share_id)->first();

        // Relative, so the redirect stays on whichever host the link was opened on rather
        // than throwing a visitor off a reseller's domain and onto ours.
        if ($post) {
            return redirect("/{$memorial->slug}/chapter/{$post->share_id}", 301);
        }

        // A tap has a share id too and nothing to show for it — the gesture is counted on
        // the memorial itself, so that is where the link goes.
        return redirect("/{$memorial->slug}", 301);
    }

    /**
     * Display a public memorial scrolled to one story (for share preview).
     */
    public function showChapter(string $memorial_slug, string $share_id)
    {
        $memorial = $this->tenantScopedMemorial($memorial_slug);

        if (! $memorial->is_public && $memorial->user_id !== auth()->id()) {
            abort(404);
        }

        if (in_array($memorial->status ?? 'active', ['deactivated', 'suspended']) && $memorial->user_id !== auth()->id() && ! auth()?->user()?->hasRole(['admin', 'super-admin'])) {
            abort(404);
        }

        if ($memorial->expires_at?->isPast()) {
            abort(404);
        }

        $post = $memorial->posts()->where('is_published', true)->where('share_id', $share_id)->firstOrFail();

        return $this->renderMemorial($memorial, $post);
    }

    /**
     * A memorial by slug, scoped to whoever's site is being served. The single-slug
     * route gets this scoping from its domain-constrained sibling; the chapter and
     * tribute deep links carry no domain constraint, so without this a link to another
     * tenant's memorial rendered that memorial on this tenant's host, wearing this
     * tenant's branding.
     */
    private function tenantScopedMemorial(string $slug): Memorial
    {
        $tenantId = \App\Helpers\ThemeSetting::siteTenantId();

        return Memorial::where('slug', $slug)
            ->when($tenantId, fn ($q) => $q->where('reseller_id', $tenantId))
            ->firstOrFail();
    }

    /**
     * The memorial's own relations, same reasoning: the life-post partial reads the post's
     * author, its chapter and its reaction count for every post in the feed.
     */
    private function loadMemorialRelations(Memorial $memorial): void
    {
        $memorial->load([
            'media',
            'galleryCategories',
            'storyChapters',
            'posts.media',
            'posts.user',
            'posts.storyChapter',
            'posts.reactions',
            'posts.comments.replies.user',
        ]);
    }

    /**
     * How many people left each gesture — the tally under the one-tap cards.
     *
     * Counts taps, not writing. Someone who wrote a candle story also lit a candle and is
     * counted here once, because their tribute row is still the record of the gesture.
     */
    private function getTributeTypeCounts(Memorial $memorial): array
    {
        $counts = $memorial->tributes()
            ->where('is_approved', true)
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type');

        $buckets = ['total' => (int) $counts->sum()];
        foreach (Tribute::TYPES as $type) {
            $buckets[$type] = (int) ($counts[$type] ?? 0);
        }

        return $buckets;
    }

    /**
     * How the feed breaks down by marker, for the filter chips above it.
     *
     * Counted off the collection the page is already rendering rather than queried again:
     * these have to agree with what the feed shows to the item, or filtering to "Candles 8"
     * turns up seven.
     *
     * `story` is the unmarked remainder — the ones nobody labelled, which is most of them.
     *
     * @param  \Illuminate\Support\Collection  $stories
     */
    private function storyMarkerCounts($stories): array
    {
        $buckets = ['total' => $stories->count(), 'story' => 0];

        foreach (Tribute::TYPES as $type) {
            $buckets[$type] = $stories->where('tribute_type', $type)->count();
        }

        $buckets['story'] = $buckets['total'] - array_sum(array_intersect_key($buckets, array_flip(Tribute::TYPES)));

        return $buckets;
    }

    private function visitorHash(Request $request): string
    {
        $ip = $request->ip() ?? '';
        $ua = $request->userAgent() ?? '';

        return hash('sha256', $ip.'|'.$ua);
    }

    private function recordView(Memorial $memorial, Request $request): void
    {
        // Fire-and-forget: analytics must never take down a public memorial page.
        try {
            $hash = $this->visitorHash($request);
            $today = Carbon::today();
            $existing = MemorialView::where('memorial_id', $memorial->id)
                ->where('visitor_hash', $hash)
                ->where('viewed_at', '>=', $today)
                ->exists();
            if (! $existing) {
                MemorialView::create([
                    'memorial_id' => $memorial->id,
                    'visitor_hash' => $hash,
                    'viewed_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Memorial view count skipped', [
                'memorial_id' => $memorial->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
