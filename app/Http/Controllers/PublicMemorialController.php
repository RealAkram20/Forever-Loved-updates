<?php

namespace App\Http\Controllers;

use App\Models\Memorial;
use App\Models\MemorialView;
use App\Models\Post;
use App\Models\Tribute;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicMemorialController extends Controller
{
    /**
     * Display a public memorial by slug (no auth required).
     */
    public function show(string $slug)
    {
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

        $canEdit = auth()->check() && (
            $memorial->user_id === auth()->id() ||
            auth()->user()->hasRole(['admin', 'super-admin'])
        );

        $stats = $this->getMemorialStats($memorial);

        return view('pages.memorials.public', [
            'title' => $memorial->full_name,
            'memorial' => $memorial,
            'tributes' => $tributes,
            'canEdit' => $canEdit,
            'isAuthenticated' => auth()->check(),
            'memorialStats' => $stats,
            'scrollToTributeId' => null,
            'scrollToChapterId' => null,
            'shareMeta' => null,
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

        $canEdit = auth()->check() && (
            $memorial->user_id === auth()->id() ||
            auth()->user()->hasRole(['admin', 'super-admin'])
        );

        $stats = $this->getMemorialStats($memorial);

        $authorName = $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous';
        $deceasedName = $memorial->full_name ?? 'Loved One';
        $age = $this->getDeceasedAge($memorial);
        $contentPreview = $tribute->message ? \Illuminate\Support\Str::limit(strip_tags($tribute->message), 150) : 'Left a ' . $tribute->type . ' in memory of ' . $deceasedName;
        $shareUrl = url()->route('memorial.tribute.public', ['memorial_slug' => $memorial->slug, 'share_id' => $tribute->share_id]);

        return view('pages.memorials.public', [
            'title' => $memorial->full_name,
            'memorial' => $memorial,
            'tributes' => $tributes,
            'highlightTribute' => $tribute,
            'canEdit' => $canEdit,
            'isAuthenticated' => auth()->check(),
            'memorialStats' => $stats,
            'scrollToTributeId' => $tribute->id,
            'scrollToChapterId' => null,
            'shareMeta' => [
                'title' => "{$authorName} · {$deceasedName}" . ($age ? " ({$age})" : ''),
                'description' => $contentPreview,
                'url' => $shareUrl,
                'image' => $memorial->profile_photo_url ? url($memorial->profile_photo_url) : null,
            ],
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

        $canEdit = auth()->check() && (
            $memorial->user_id === auth()->id() ||
            auth()->user()->hasRole(['admin', 'super-admin'])
        );

        $stats = $this->getMemorialStats($memorial);

        $authorName = $post->user?->name ?? $memorial->full_name ?? 'Anonymous';
        $deceasedName = $memorial->full_name ?? 'Loved One';
        $age = $this->getDeceasedAge($memorial);
        $contentPreview = $post->content ? \Illuminate\Support\Str::limit(strip_tags($post->content), 150) : ($post->title ?? 'A chapter in memory of ' . $deceasedName);
        $shareUrl = url()->route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id]);

        return view('pages.memorials.public', [
            'title' => $memorial->full_name,
            'memorial' => $memorial,
            'tributes' => $tributes,
            'canEdit' => $canEdit,
            'isAuthenticated' => auth()->check(),
            'memorialStats' => $stats,
            'scrollToTributeId' => null,
            'scrollToChapterId' => $post->id,
            'shareMeta' => [
                'title' => "{$authorName} · {$deceasedName}" . ($age ? " ({$age})" : ''),
                'description' => $contentPreview,
                'url' => $shareUrl,
                'image' => $memorial->profile_photo_url ? url($memorial->profile_photo_url) : null,
            ],
        ]);
    }

    private function getDeceasedAge(Memorial $memorial): ?string
    {
        $birth = $memorial->date_of_birth;
        $death = $memorial->date_of_passing;
        if (!$birth || !$death) {
            return null;
        }
        $age = $death->diffInYears($birth);
        return $age . ' years';
    }

    private function visitorHash(Request $request): string
    {
        $ip = $request->ip() ?? '';
        $ua = $request->userAgent() ?? '';
        return hash('sha256', $ip . '|' . $ua);
    }

    private function recordView(Memorial $memorial, Request $request): void
    {
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
    }

    private function getMemorialStats(Memorial $memorial): array
    {
        $today = Carbon::today();
        $lastWeek = Carbon::today()->subDays(7);

        $viewsToday = (int) MemorialView::where('memorial_id', $memorial->id)
            ->where('viewed_at', '>=', $today)
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');
        $viewsLastWeek = (int) MemorialView::where('memorial_id', $memorial->id)
            ->where('viewed_at', '>=', $lastWeek)
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');
        $viewsAllTime = (int) MemorialView::where('memorial_id', $memorial->id)
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');

        $sharesToday = (int) \App\Models\MemorialShare::where('memorial_id', $memorial->id)
            ->where('shared_at', '>=', $today)
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');
        $sharesLastWeek = (int) \App\Models\MemorialShare::where('memorial_id', $memorial->id)
            ->where('shared_at', '>=', $lastWeek)
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');
        $sharesAllTime = (int) \App\Models\MemorialShare::where('memorial_id', $memorial->id)
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');

        return [
            'views_today' => $viewsToday,
            'views_last_week' => $viewsLastWeek,
            'views_all_time' => $viewsAllTime,
            'shares_today' => $sharesToday,
            'shares_last_week' => $sharesLastWeek,
            'shares_all_time' => $sharesAllTime,
        ];
    }

    /**
     * Store a tribute (flower, candle, note) - guest or authenticated.
     */
    public function storeTribute(Request $request, string $slug)
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (!$memorial->is_public) {
            abort(404);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:flower,candle,note'],
            'message' => ['nullable', 'string', 'max:2000'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email'],
        ]);

        $guestName = $request->user()?->name ?? $validated['guest_name'] ?? 'Anonymous';
        $guestEmail = $request->user()?->email ?? $validated['guest_email'] ?? null;

        Tribute::create([
            'memorial_id' => $memorial->id,
            'user_id' => $request->user()?->id,
            'type' => $validated['type'],
            'message' => $validated['message'] ?? null,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'is_approved' => true,
        ]);

        return back()->with('status', 'Thank you for your tribute.');
    }
}
