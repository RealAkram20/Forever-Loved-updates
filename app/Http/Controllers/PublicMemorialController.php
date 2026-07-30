<?php

namespace App\Http\Controllers;

use App\Helpers\MemorialShareMetaHelper;
use App\Helpers\MemorialStatsHelper;
use App\Helpers\PlanLimitsHelper;
use App\Models\Memorial;
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

    private function renderMemorial(Memorial $memorial)
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

        $tributes = $memorial->tributes()
            ->where('is_approved', true)
            ->with(['user', 'comments'])
            ->latest()
            ->paginate(20);

        $memorial->load('media', 'posts.media', 'posts.comments', 'storyChapters');

        $canEdit = $memorial->canBeEditedBy(auth()->user());

        $stats = MemorialStatsHelper::get($memorial);
        $tributeCounts = $this->getTributeTypeCounts($memorial);

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
            'tributes' => $tributes,
            'canEdit' => $canEdit,
            'isAuthenticated' => auth()->check(),
            'memorialStats' => $stats,
            'tributeCounts' => $tributeCounts,
            'quotaInfo' => PlanLimitsHelper::getQuotaInfo($memorial),
            'scrollToTributeId' => null,
            'scrollToChapterId' => null,
            'shareMeta' => MemorialShareMetaHelper::forMemorial($memorial),
            'pendingPaymentOrder' => $pendingPaymentOrder,
        ]);
    }

    /**
     * Display a public memorial with a specific tribute (for share preview).
     */
    public function showTribute(string $memorial_slug, string $share_id)
    {
        $memorial = Memorial::where('slug', $memorial_slug)->firstOrFail();

        if (! $memorial->is_public && $memorial->user_id !== auth()->id()) {
            abort(404);
        }

        if (in_array($memorial->status ?? 'active', ['deactivated', 'suspended']) && $memorial->user_id !== auth()->id() && ! auth()?->user()?->hasRole(['admin', 'super-admin'])) {
            abort(404);
        }

        if ($memorial->expires_at?->isPast()) {
            abort(404);
        }

        $tribute = $memorial->tributes()->where('is_approved', true)->where('share_id', $share_id)->with(['user', 'comments'])->firstOrFail();

        if ($memorial->is_public) {
            $this->recordView($memorial, request());
        }

        $tributes = $memorial->tributes()
            ->where('is_approved', true)
            ->where('id', '!=', $tribute->id)
            ->with(['user', 'comments'])
            ->latest()
            ->paginate(20);

        $memorial->load('media', 'posts.media', 'posts.comments', 'storyChapters');

        $canEdit = $memorial->canBeEditedBy(auth()->user());

        $stats = MemorialStatsHelper::get($memorial);

        $tributeCounts = $this->getTributeTypeCounts($memorial);

        return view('pages.memorials.public', [
            'title' => $memorial->full_name,
            'memorial' => $memorial,
            'tributes' => $tributes,
            'highlightTribute' => $tribute,
            'canEdit' => $canEdit,
            'isAuthenticated' => auth()->check(),
            'memorialStats' => $stats,
            'tributeCounts' => $tributeCounts,
            'quotaInfo' => PlanLimitsHelper::getQuotaInfo($memorial),
            'scrollToTributeId' => $tribute->id,
            'scrollToChapterId' => null,
            'shareMeta' => MemorialShareMetaHelper::forTribute($memorial, $tribute),
        ]);
    }

    /**
     * Display a public memorial with a specific chapter (for share preview).
     */
    public function showChapter(string $memorial_slug, string $share_id)
    {
        $memorial = Memorial::where('slug', $memorial_slug)->firstOrFail();

        if (! $memorial->is_public && $memorial->user_id !== auth()->id()) {
            abort(404);
        }

        if (in_array($memorial->status ?? 'active', ['deactivated', 'suspended']) && $memorial->user_id !== auth()->id() && ! auth()?->user()?->hasRole(['admin', 'super-admin'])) {
            abort(404);
        }

        if ($memorial->expires_at?->isPast()) {
            abort(404);
        }

        $post = $memorial->posts()->where('is_published', true)->where('share_id', $share_id)->with(['user', 'media', 'storyChapter'])->firstOrFail();

        if ($memorial->is_public) {
            $this->recordView($memorial, request());
        }

        $tributes = $memorial->tributes()
            ->where('is_approved', true)
            ->with(['user', 'comments'])
            ->latest()
            ->paginate(20);

        $memorial->load('media', 'posts.media', 'posts.comments', 'storyChapters');

        $canEdit = $memorial->canBeEditedBy(auth()->user());

        $stats = MemorialStatsHelper::get($memorial);

        $tributeCounts = $this->getTributeTypeCounts($memorial);

        return view('pages.memorials.public', [
            'title' => $memorial->full_name,
            'memorial' => $memorial,
            'tributes' => $tributes,
            'canEdit' => $canEdit,
            'isAuthenticated' => auth()->check(),
            'memorialStats' => $stats,
            'tributeCounts' => $tributeCounts,
            'quotaInfo' => PlanLimitsHelper::getQuotaInfo($memorial),
            'scrollToTributeId' => null,
            'scrollToChapterId' => $post->id,
            'shareMeta' => MemorialShareMetaHelper::forChapter($memorial, $post),
        ]);
    }

    private function getTributeTypeCounts(Memorial $memorial): array
    {
        $counts = $memorial->tributes()
            ->where('is_approved', true)
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type');

        return [
            'flower' => (int) ($counts['flower'] ?? 0),
            'candle' => (int) ($counts['candle'] ?? 0),
            'note' => (int) ($counts['note'] ?? 0),
            'total' => (int) $counts->sum(),
        ];
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
