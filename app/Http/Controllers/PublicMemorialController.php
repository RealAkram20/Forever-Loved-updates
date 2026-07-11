<?php

namespace App\Http\Controllers;

use App\Helpers\MemorialShareMetaHelper;
use App\Helpers\MemorialStatsHelper;
use App\Helpers\PlanLimitsHelper;
use App\Models\Memorial;
use App\Models\MemorialView;
use App\Models\Post;
use App\Models\Tribute;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicMemorialController extends Controller
{
    /**
     * Display a public memorial by slug (no auth required).
     * CMS pages are checked first so custom pages live at /{slug} without the /p/ prefix.
     */
    public function show(string $slug)
    {
        $cmsPage = \App\Models\Page::getBySlug($slug);
        if ($cmsPage && $cmsPage->is_published && ! $cmsPage->isSystemLayoutPage()) {
            return app(\App\Http\Controllers\PageController::class)->showPage($slug);
        }

        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        // Allow owner to view their own memorial even if private
        if (!$memorial->is_public && $memorial->user_id !== auth()->id()) {
            abort(404);
        }

        // Deactivated/suspended memorials are hidden from public (admin can still view via dashboard)
        if (in_array($memorial->status ?? 'active', ['deactivated', 'suspended']) && $memorial->user_id !== auth()->id() && !auth()?->user()?->hasRole(['admin', 'super-admin'])) {
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

        // Owner-only nudge: paid-plan checkout was started but never completed
        // (Phase-2 dedup guarantees at most one pending order per purchase).
        $pendingPaymentOrder = null;
        if (auth()->id() && auth()->id() === $memorial->user_id) {
            $pendingPaymentOrder = \App\Models\PaymentOrder::where('memorial_id', $memorial->id)
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

        if (!$memorial->is_public && $memorial->user_id !== auth()->id()) {
            abort(404);
        }

        if (in_array($memorial->status ?? 'active', ['deactivated', 'suspended']) && $memorial->user_id !== auth()->id() && !auth()?->user()?->hasRole(['admin', 'super-admin'])) {
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

        if (!$memorial->is_public && $memorial->user_id !== auth()->id()) {
            abort(404);
        }

        if (in_array($memorial->status ?? 'active', ['deactivated', 'suspended']) && $memorial->user_id !== auth()->id() && !auth()?->user()?->hasRole(['admin', 'super-admin'])) {
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
            ->selectRaw("type, COUNT(*) as cnt")
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
        return hash('sha256', $ip . '|' . $ua);
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
            if (!$existing) {
                MemorialView::create([
                    'memorial_id' => $memorial->id,
                    'visitor_hash' => $hash,
                    'viewed_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Memorial view count skipped', [
                'memorial_id' => $memorial->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
