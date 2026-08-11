<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlHelper;
use App\Helpers\MemorialStatsHelper;
use App\Helpers\PlanLimitsHelper;
use App\Models\Comment;
use App\Models\Memorial;
use App\Models\MemorialShare;
use App\Models\MemorialSubscription;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Tribute;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\GuestIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemorialApiController extends Controller
{
    /**
     * Update a memorial section (inline edit). Admin or owner only.
     */
    public function updateSection(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'section' => ['required', 'string', 'in:full_name,biography,date_of_birth,date_of_passing,dates'],
            'value' => ['nullable', 'string', 'max:50000'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
        ]);

        $section = $validated['section'];
        $value = $validated['value'] ?? '';

        if ($section === 'full_name') {
            $memorial->update(['full_name' => trim($value)]);
        } elseif ($section === 'biography') {
            $memorial->update(['biography' => trim($value) ?: null]);
        } elseif ($section === 'date_of_birth') {
            $memorial->update(['date_of_birth' => $value ?: null]);
        } elseif ($section === 'date_of_passing') {
            $memorial->update(['date_of_passing' => $value ?: null]);
        } elseif ($section === 'dates') {
            $memorial->update([
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'date_of_passing' => $validated['date_of_passing'] ?? null,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Record a tap on one of the one-tap cards — a flower, a candle, a prayer.
     *
     * Taps only. This endpoint used to take a `message` as well, which is what made a
     * tribute a second kind of written post and forced the memorial page to carry two
     * feeds and a sub-tab to choose between them. Words now go to
     * MemorialMediaController::storeTributePost() and become a story carrying the same
     * marker, so there is one feed and one composer. A `message` sent here is ignored
     * rather than rejected: an old client posting one still gets its tap counted.
     */
    public function storeTribute(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (! $memorial->is_public) {
            return response()->json(['error' => 'Memorial is not public'], 404);
        }

        $userId = $request->user()?->id;
        if ($userId) {
            $request->merge(['user_id' => $userId]);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(Tribute::TYPES)],
            'guest_name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'guest_email' => ['required_without:user_id', 'nullable', 'email'],
        ]);

        $guestName = $request->user()?->name ?? $validated['guest_name'] ?? null;
        $guestEmail = $request->user()?->email ?? $validated['guest_email'] ?? null;

        if (! $userId && (! $guestName || ! $guestEmail)) {
            return response()->json(['error' => 'Name and email are required for guests'], 422);
        }

        // A registered address belongs to its owner, not to whoever typed it. Adopting the
        // match here published the tribute under that member's name and photo.
        if (! $userId && GuestIdentity::isRegistered($guestEmail)) {
            return GuestIdentity::requiresLoginResponse('leave a tribute');
        }

        // Unknown address: create the account this flow has always created for them.
        if (! $userId && $guestEmail) {
            $user = User::create([
                'name' => $guestName,
                'email' => strtolower($guestEmail),
                'password' => null,
            ]);

            NotificationService::notifyNewUserSignup($user);

            $setupUrl = route('password.request');
            \App\Support\ReliableDispatch::dispatch(new \App\Jobs\SendRawEmail(
                to: $guestEmail,
                name: $guestName,
                subject: 'Welcome to Forever-Loved - Complete your account',
                body: "Welcome to Forever-Loved!\n\nYou've left a tribute. To complete your account and set a password, visit: {$setupUrl}\n\nYou can also sign in with a one-time code at: ".route('login.passwordless'),
            ));

            $userId = $user->id;
            $guestName = $user->name;
        }

        // One tribute of each kind per person, so a tally means "how many people lit a
        // candle" rather than "how many times anyone tapped". Tapping again is not an error
        // — the endpoint is idempotent and hands back the tribute they already left, which
        // lets the page keep playing its burst without inflating the count.
        //
        // Keyed on user_id alone: the guest branch above turns every new address into an
        // account before reaching here, so a signed-out visitor is a user by this point too.
        $existing = $userId
            ? Tribute::where('memorial_id', $memorial->id)
                ->where('user_id', $userId)
                ->where('type', $validated['type'])
                ->first()
            : null;

        if ($existing) {
            $existing->load('user');

            return response()->json([
                'success' => true,
                'duplicate' => true,
                'tribute' => $this->tributePayload($existing),
            ]);
        }

        $tribute = Tribute::create([
            'memorial_id' => $memorial->id,
            'user_id' => $userId,
            'type' => $validated['type'],
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'is_approved' => true,
        ]);

        $tribute->load('user');

        $authorName = $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous';
        NotificationService::notifyNewTribute($memorial, $validated['type'], $authorName, $userId, $tribute);

        return response()->json([
            'success' => true,
            'duplicate' => false,
            'tribute' => $this->tributePayload($tribute),
        ]);
    }

    /**
     * The shape the memorial page expects for a single tribute. Shared by the created and
     * already-existed branches of storeTribute() so the two can never drift apart.
     */
    private function tributePayload(Tribute $tribute): array
    {
        return [
            'id' => $tribute->id,
            'share_id' => $tribute->share_id,
            'type' => $tribute->type,
            'author' => $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous',
            'author_photo' => $tribute->user?->profile_photo_url,
            'created_at' => $tribute->created_at->diffForHumans(),
            'created_at_iso' => $tribute->created_at->toIso8601String(),
        ];
    }

    /**
     * Store a reaction (like, love, candle, flower) on a post or tribute.
     */
    public function storeReaction(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (! $memorial->is_public) {
            return response()->json(['error' => 'Memorial is not public'], 404);
        }

        $validated = $request->validate([
            'reactionable_type' => ['required', 'in:post,tribute,comment'],
            'reactionable_id' => ['required', 'integer'],
            'type' => ['required', 'in:like,love,candle,flower'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email'],
        ]);

        $userId = $request->user()?->id;
        $guestName = $validated['guest_name'] ?? null;
        $guestEmail = $validated['guest_email'] ?? null;

        if (! $userId && (! $guestName || ! $guestEmail)) {
            return response()->json([
                'error' => 'Name and email required',
                'requires_guest_info' => true,
            ], 422);
        }

        // If guest with existing account, ask them to log in
        if (! $userId && $guestEmail) {
            $existingUser = User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                return response()->json([
                    'error' => 'An account exists with this email. Please log in to react.',
                    'requires_login' => true,
                ], 422);
            }

            // Create new user for first-time reactor
            $user = User::create([
                'name' => $guestName,
                'email' => strtolower($guestEmail),
                'password' => null,
            ]);

            \App\Support\ReliableDispatch::dispatch(new \App\Jobs\SendRawEmail(
                to: $guestEmail,
                name: $guestName,
                subject: 'Welcome to Forever-Loved',
                body: 'Welcome to Forever-Loved! You can sign in with a one-time code at: '.route('login.passwordless'),
            ));

            $userId = $user->id;
        }

        // Every branch is scoped to this memorial before anything is written. A comment has
        // no memorial_id of its own, so it is reached through the story it hangs off —
        // without that, this endpoint would happily like a comment on a private memorial
        // (or another reseller's) by id, and hand back its tally as confirmation it exists.
        $modelClass = match ($validated['reactionable_type']) {
            'post' => Post::class,
            'comment' => Comment::class,
            default => Tribute::class,
        };

        $reactionable = $modelClass === Comment::class
            ? Comment::whereHas('post', fn ($q) => $q->where('memorial_id', $memorial->id))
                ->where('is_approved', true)
                ->findOrFail($validated['reactionable_id'])
            : $modelClass::where('memorial_id', $memorial->id)->findOrFail($validated['reactionable_id']);

        $existing = Reaction::where('reactionable_type', $modelClass)
            ->where('reactionable_id', $reactionable->id)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(! $userId, fn ($q) => $q->where('guest_email', $guestEmail))
            ->where('type', $validated['type'])
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            Reaction::create([
                'reactionable_type' => $modelClass,
                'reactionable_id' => $reactionable->id,
                'user_id' => $userId,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'type' => $validated['type'],
            ]);
            $action = 'added';
        }

        $count = $reactionable->reactions()->count();

        return response()->json([
            'success' => true,
            'action' => $action,
            'count' => $count,
        ]);
    }

    /**
     * List posts for memorial Life feed.
     */
    public function posts(string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canRead($memorial)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $posts = $memorial->posts()
            ->where('is_published', true)
            ->with(['user', 'memorial', 'reactions', 'media', 'storyChapter'])
            ->withCount('reactions')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($p) => $this->formatPost($p));

        return response()->json(['posts' => $posts]);
    }

    /**
     * List story chapters for Life tab.
     */
    public function chapters(string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canRead($memorial)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $chapters = $memorial->storyChapters()->orderBy('sort_order')->get();

        return response()->json(['chapters' => $chapters]);
    }

    /**
     * Store a story chapter. Admin or owner only.
     */
    public function storeChapter(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (! $mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $chapterCheck = PlanLimitsHelper::canAddChapter($memorial);
        if (! $chapterCheck['allowed']) {
            return response()->json([
                'error' => "Chapter limit reached ({$chapterCheck['current']}/{$chapterCheck['max']}). Upgrade your plan for more.",
            ], 422);
        }

        $chapter = $memorial->storyChapters()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $memorial->storyChapters()->max('sort_order') + 1,
        ]);

        NotificationService::notifyNewLifeChapter($memorial, $validated['title'], auth()->id(), null, auth()->user()?->name);

        return response()->json(['success' => true, 'chapter' => $chapter]);
    }

    /**
     * Update a story chapter. Admin, owner, or collaborator only.
     */
    public function updateChapter(Request $request, string $slug, int $chapterId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (! $mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $chapter = $memorial->storyChapters()->findOrFail($chapterId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $chapter->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json(['success' => true, 'chapter' => $chapter->fresh()]);
    }

    /**
     * Delete a story chapter. Admin, owner, or collaborator only.
     * Posts in the chapter are unlinked (set to null), not deleted.
     */
    public function deleteChapter(string $slug, int $chapterId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (! $mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $chapter = $memorial->storyChapters()->findOrFail($chapterId);

        $chapter->posts()->update(['story_chapter_id' => null]);
        $chapter->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Store a post. Admin or owner only.
     */
    public function storePost(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (! $mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:text,image,location,gallery'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $post = $memorial->posts()->create([
            'user_id' => $request->user()?->id,
            'type' => $validated['type'],
            'title' => $validated['title'] ?? null,
            'content' => HtmlHelper::sanitize($validated['content'] ?? null) ?: null,
            'location' => $validated['location'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        $chapterTitle = $post->title ?: ($post->storyChapter?->title ?? 'A chapter');
        NotificationService::notifyNewLifeChapter($memorial, $chapterTitle, $request->user()?->id, $post);

        return response()->json(['success' => true, 'post' => $this->formatPost($post)]);
    }

    /**
     * Update a post. Admin or owner only.
     */
    public function updatePost(Request $request, string $slug, int $postId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        $post = $memorial->posts()->findOrFail($postId);

        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (! $mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            // Present and empty means "make it a plain story again", which is why this is
            // nullable rather than filtered out: someone has to be able to take a marker
            // off as easily as they put one on.
            'tribute_type' => ['nullable', Rule::in(Tribute::TYPES)],
        ]);

        // Sanitised on the way in, like every other rich-text write. This one was passing
        // the validated array straight through, and the Life feed assigns the response's
        // `content` to innerHTML — so an editor's markup reached the DOM twice unfiltered.
        if (array_key_exists('content', $validated)) {
            $validated['content'] = HtmlHelper::sanitize($validated['content']) ?: null;
        }

        $post->update($validated);

        return response()->json(['success' => true, 'post' => $this->formatPost($post->fresh())]);
    }

    /**
     * Delete a post. Admin or owner only.
     */
    public function deletePost(string $slug, int $postId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        $post = $memorial->posts()->findOrFail($postId);

        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (! $mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $post->delete();

        return response()->json(['success' => true]);
    }

    /**
     * List the taps left on a memorial — who lit a candle, who left a flower.
     *
     * Not the feed. The feed is stories: see posts().
     */
    public function tributes(string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canRead($memorial)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $tributes = $memorial->tributes()
            ->where('is_approved', true)
            ->with(['user', 'reactions'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'tributes' => $tributes->items(),
            'total' => $tributes->total(),
        ]);
    }

    /**
     * Update a tribute. Memorial editor OR tribute author.
     */
    public function updateTribute(Request $request, string $slug, int $tributeId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        $tribute = $memorial->tributes()->findOrFail($tributeId);

        if (! $this->canEditTribute($memorial, $tribute)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(Tribute::TYPES)],
            'message' => ['nullable', 'string', 'max:10000'],
        ]);

        $tribute->update(array_filter([
            'type' => $validated['type'] ?? null,
            'message' => array_key_exists('message', $validated) ? HtmlHelper::sanitize($validated['message']) : null,
        ], fn ($v) => $v !== null));

        $tribute->load('user');
        $authorName = $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous';

        return response()->json([
            'success' => true,
            'tribute' => [
                'id' => $tribute->id,
                'share_id' => $tribute->share_id,
                'type' => $tribute->type,
                'message' => $tribute->message,
                'author' => $authorName,
                'author_photo' => $tribute->user?->profile_photo_url,
            ],
        ]);
    }

    /**
     * Delete a tribute. Memorial editor OR tribute author.
     */
    public function deleteTribute(string $slug, int $tributeId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        $tribute = $memorial->tributes()->findOrFail($tributeId);

        if (! $this->canEditTribute($memorial, $tribute)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tribute->reactions()->delete();
        $tribute->comments()->each(function ($comment) {
            $comment->replies()->delete();
            $comment->delete();
        });
        $tribute->delete();

        return response()->json(['success' => true]);
    }

    private function canEditTribute(Memorial $memorial, Tribute $tribute): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($this->canEdit($memorial)) {
            return true;
        }

        return $tribute->user_id && $tribute->user_id === $user->id;
    }

    /**
     * Subscribe to a memorial for notifications.
     */
    public function subscribe(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (! PlanLimitsHelper::canUseGuestNotifications($memorial)) {
            return response()->json(['error' => 'Guest notifications are not available on this memorial\'s plan.'], 422);
        }

        $validated = $request->validate([
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'notify_life_chapters' => ['nullable', 'boolean'],
            'notify_tributes' => ['nullable', 'boolean'],
        ]);

        $userId = $request->user()?->id;
        $guestName = $validated['guest_name'] ?? null;
        $guestEmail = $validated['guest_email'] ?? null;

        // Resolving the address to its account let anyone subscribe a member — and, via
        // update/unsubscribe below, read and change that member's notification settings.
        if (! $userId && GuestIdentity::isRegistered($guestEmail)) {
            return GuestIdentity::requiresLoginResponse('manage notifications');
        }

        if (! $userId && ! $guestEmail) {
            return response()->json(['error' => 'Email is required'], 422);
        }

        $sub = MemorialSubscription::updateOrCreate(
            $userId
                ? ['memorial_id' => $memorial->id, 'user_id' => $userId]
                : ['memorial_id' => $memorial->id, 'guest_email' => strtolower($guestEmail)],
            [
                'user_id' => $userId,
                'guest_name' => $userId ? null : $guestName,
                'guest_email' => $userId ? null : strtolower($guestEmail),
                'notify_life_chapters' => $validated['notify_life_chapters'] ?? true,
                'notify_tributes' => $validated['notify_tributes'] ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'subscription' => [
                'id' => $sub->id,
                'name' => $sub->subscriber_name,
                'notify_life_chapters' => $sub->notify_life_chapters,
                'notify_tributes' => $sub->notify_tributes,
            ],
        ]);
    }

    /**
     * Update subscription notification preferences.
     */
    public function updateSubscription(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'guest_email' => ['nullable', 'email'],
            'notify_life_chapters' => ['required', 'boolean'],
            'notify_tributes' => ['required', 'boolean'],
        ]);

        $userId = $request->user()?->id;
        $guestEmail = $validated['guest_email'] ?? null;

        if (! $userId && GuestIdentity::isRegistered($guestEmail)) {
            return GuestIdentity::requiresLoginResponse('manage notifications');
        }

        $sub = $userId
            ? MemorialSubscription::where('memorial_id', $memorial->id)->where('user_id', $userId)->first()
            : ($guestEmail ? MemorialSubscription::where('memorial_id', $memorial->id)->where('guest_email', strtolower($guestEmail))->first() : null);

        if (! $sub) {
            return response()->json(['error' => 'Subscription not found'], 404);
        }

        $sub->update([
            'notify_life_chapters' => $validated['notify_life_chapters'],
            'notify_tributes' => $validated['notify_tributes'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Unsubscribe from a memorial.
     */
    public function unsubscribe(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        $userId = $request->user()?->id;
        $guestEmail = $request->input('guest_email');

        if (! $userId && GuestIdentity::isRegistered($guestEmail)) {
            return GuestIdentity::requiresLoginResponse('manage notifications');
        }

        $deleted = $userId
            ? MemorialSubscription::where('memorial_id', $memorial->id)->where('user_id', $userId)->delete()
            : ($guestEmail ? MemorialSubscription::where('memorial_id', $memorial->id)->where('guest_email', strtolower($guestEmail))->delete() : 0);

        return response()->json(['success' => $deleted > 0]);
    }

    /**
     * Check subscription status for current user or guest email.
     */
    public function checkSubscription(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        $userId = $request->user()?->id;
        $guestEmail = $request->query('email');

        // Answering for a registered address made this an oracle: it confirmed the address
        // had an account and handed back that person's notification preferences.
        if (! $userId && GuestIdentity::isRegistered($guestEmail)) {
            return response()->json(['subscribed' => false]);
        }

        $sub = $userId
            ? MemorialSubscription::where('memorial_id', $memorial->id)->where('user_id', $userId)->first()
            : ($guestEmail ? MemorialSubscription::where('memorial_id', $memorial->id)->where('guest_email', strtolower($guestEmail))->first() : null);

        if (! $sub) {
            return response()->json(['subscribed' => false]);
        }

        return response()->json([
            'subscribed' => true,
            'subscription' => [
                'id' => $sub->id,
                'name' => $sub->subscriber_name,
                'notify_life_chapters' => $sub->notify_life_chapters,
                'notify_tributes' => $sub->notify_tributes,
            ],
        ]);
    }

    /**
     * Delete a comment. Memorial owner or admin/super-admin only.
     */
    public function deleteComment(Request $request, string $slug, int $commentId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $comment = Comment::where('id', $commentId)
            ->whereHas('post', fn ($q) => $q->where('memorial_id', $memorial->id))
            ->first();

        if (! $comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        $replyCount = $comment->replies()->count();
        $comment->replies()->delete();
        $comment->delete();

        return response()->json([
            'success' => true,
            'deleted_count' => 1 + $replyCount,
        ]);
    }

    private function canEdit(Memorial $memorial): bool
    {
        return $memorial->canBeEditedBy(auth()->user());
    }

    /**
     * May the caller *read* this memorial's content through the API?
     *
     * The read endpoints were split three ways: comments(), reactions() and stats() checked
     * `is_public`, while posts(), tributes() and chapters() checked nothing at all — so a
     * private memorial's life feed, tributes and chapters were served to anyone who knew the
     * slug, even though the page itself 404s for them. One gate for all six.
     *
     * It also has to admit the people who can edit: the page deliberately lets an owner view
     * their own unpublished memorial, and the strict `is_public` endpoints were returning 404
     * to them on their own content.
     */
    private function canRead(Memorial $memorial): bool
    {
        return $memorial->is_public || $this->canEdit($memorial);
    }

    /**
     * Get comments for a post.
     */
    /** Replies shown under a comment before it offers "View all N replies". */
    private const REPLY_PREVIEW = 3;

    /**
     * A page of a story's comments, for the comment sheet.
     *
     * This used to return every comment on the story in one array. That is fine for the
     * three a memorial starts with and useless at two hundred, which is the number the
     * sheet is built for — so it pages, keyed on id rather than an offset. Ids are stable
     * while people are posting; an offset is not, and paging by one drops or repeats a
     * comment every time somebody adds one mid-scroll.
     *
     * Three modes, one endpoint:
     *   (none)   the first page, newest first
     *   ?before  the next page down
     *   ?after   everything posted since — what the open sheet polls for
     */
    public function comments(Request $request, string $slug, int $postId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canRead($memorial)) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $post = $memorial->posts()->findOrFail($postId);

        $validated = $request->validate([
            'before' => ['nullable', 'integer'],
            'after' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $limit = (int) ($validated['limit'] ?? 20);

        $base = fn () => Comment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->where('is_approved', true)
            ->with(['user', 'replies.user'])
            ->withCount('reactions');

        // Polling: not a page but a gap. Oldest first, so they append to the top of the
        // list in the order they were written rather than back to front.
        if (! empty($validated['after'])) {
            $rows = $base()->where('id', '>', $validated['after'])->orderBy('id')->limit(50)->get();

            return response()->json([
                'comments' => $this->commentPayloads($rows, $request, $memorial),
                'total' => $this->commentTotal($post),
                'has_more' => false,
            ]);
        }

        $rows = $base()
            ->when(! empty($validated['before']), fn ($q) => $q->where('id', '<', $validated['before']))
            ->orderByDesc('id')
            // One extra, purely to answer "is there another page" without a second count.
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;

        return response()->json([
            'comments' => $this->commentPayloads($rows->take($limit), $request, $memorial),
            'total' => $this->commentTotal($post),
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Every reply under one comment, for "View all N replies".
     */
    public function commentReplies(Request $request, string $slug, int $commentId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canRead($memorial)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $comment = Comment::whereHas('post', fn ($q) => $q->where('memorial_id', $memorial->id))
            ->where('is_approved', true)
            ->findOrFail($commentId);

        $replies = $comment->replies()->withCount('reactions')->get()->reverse()->values();

        return response()->json([
            'replies' => $this->commentPayloads($replies, $request, $memorial, false),
        ]);
    }

    /**
     * What the sheet header counts: everything anyone wrote on the story, replies included.
     * "176 comments" that turns out to mean "176 top-level comments" is a lie the moment
     * somebody expands a thread.
     */
    private function commentTotal(Post $post): int
    {
        return (int) Comment::where('post_id', $post->id)->where('is_approved', true)->count();
    }

    /**
     * @param  \Illuminate\Support\Collection  $comments
     * @param  bool  $withReplies  Replies are themselves comments and have none of their own.
     */
    private function commentPayloads($comments, Request $request, Memorial $memorial, bool $withReplies = true): array
    {
        // One boolean for the whole page, not one per row: deleteComment() authorises on
        // the memorial, so whoever may delete one comment may delete all of them.
        $canDelete = $this->canEdit($memorial);

        // Whether *you* hearted each one, resolved in a single query for the whole page
        // rather than a relation load per row. Guests get false: a reaction is keyed to an
        // account or an address, and an anonymous reader has offered neither yet.
        $liked = [];
        $userId = $request->user()?->id;
        if ($userId && $comments->isNotEmpty()) {
            $ids = $comments->pluck('id');
            if ($withReplies) {
                $ids = $ids->merge($comments->flatMap(fn ($c) => $c->replies->pluck('id')));
            }
            $liked = Reaction::where('reactionable_type', Comment::class)
                ->whereIn('reactionable_id', $ids->all())
                ->where('user_id', $userId)
                ->pluck('reactionable_id')
                ->flip()
                ->all();
        }

        return $comments->map(function (Comment $c) use ($liked, $withReplies, $canDelete) {
            $payload = $this->commentPayload($c, $liked, $canDelete);

            if ($withReplies) {
                $replies = $c->replies;
                $payload['reply_count'] = $replies->count();
                // Newest last under a parent: a thread reads downward, unlike the list it
                // hangs in. The preview keeps the most recent ones, then flips them back.
                $payload['replies'] = $replies->take(self::REPLY_PREVIEW)->reverse()
                    ->map(fn (Comment $r) => $this->commentPayload($r, $liked, $canDelete))
                    ->values()
                    ->all();
            }

            return $payload;
        })->values()->all();
    }

    private function commentPayload(Comment $c, array $liked, bool $canDelete): array
    {
        return [
            'id' => $c->id,
            'parent_id' => $c->parent_id,
            'content' => $c->content,
            'author' => $c->author_name,
            'author_photo' => $c->user?->profile_photo_url,
            'created_at' => $c->created_at->diffForHumans(short: true),
            'created_at_iso' => $c->created_at->toIso8601String(),
            'reaction_count' => (int) ($c->reactions_count ?? 0),
            'reacted' => isset($liked[$c->id]),
            'can_delete' => $canDelete,
        ];
    }

    /**
     * Store a comment on a post. Guest or authenticated.
     */
    public function storeComment(Request $request, string $slug, int $postId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $memorial->is_public) {
            return response()->json(['error' => 'Memorial is not public'], 404);
        }
        $post = $memorial->posts()->findOrFail($postId);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email'],
        ]);

        $userId = $request->user()?->id;
        $guestName = $request->user()?->name ?? $validated['guest_name'] ?? null;
        $guestEmail = $request->user()?->email ?? $validated['guest_email'] ?? null;

        if (! $userId && (! $guestName || ! $guestEmail)) {
            return response()->json(['error' => 'Name and email are required for guests'], 422);
        }

        // Typing a member's address must not sign their name to your comment.
        if (! $userId && GuestIdentity::isRegistered($guestEmail)) {
            return GuestIdentity::requiresLoginResponse('comment');
        }

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            $parent = Comment::where('post_id', $post->id)->find($parentId);
            if (! $parent) {
                return response()->json(['error' => 'Invalid parent comment'], 422);
            }
        }

        $comment = Comment::create([
            'post_id' => $post->id,
            'parent_id' => $parentId,
            'user_id' => $userId,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'content' => trim($validated['content']),
            'is_approved' => true,
        ]);

        NotificationService::notifyCommentOnChapter($post, $comment, $userId);

        $comment->load('user');
        $comment->reactions_count = 0;

        // The same shape the list returns, so the sheet renders a comment it just posted
        // through exactly the same path as one it fetched — there is one row renderer and
        // no second, subtly-different version of it to drift.
        return response()->json([
            'success' => true,
            'comment' => $this->commentPayload($comment, [], $this->canEdit($memorial)) + [
                'reply_count' => 0,
                'replies' => [],
            ],
            'total' => $this->commentTotal($post),
        ]);
    }

    /**
     * Get reactions for a post (for dropdown).
     */
    public function reactions(string $slug, int $postId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canRead($memorial)) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $post = $memorial->posts()->findOrFail($postId);
        $reactions = $post->reactions()->with('user')->get()->map(fn ($r) => [
            'type' => $r->type,
            'name' => $r->user?->name ?? $r->guest_name ?? 'Anonymous',
        ]);

        return response()->json([
            'reactions' => $reactions,
            'count' => $reactions->count(),
        ]);
    }

    /**
     * Record a memorial profile share (deduped: one record per visitor per type per day).
     * Returns updated stats so the frontend can refresh counters immediately.
     */
    public function trackShare(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $memorial->is_public) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if (! PlanLimitsHelper::canShareMemories($memorial)) {
            return response()->json(['error' => 'Sharing is not available on this memorial\'s plan.'], 422);
        }

        $validated = $request->validate([
            'share_type' => ['required', 'in:whatsapp,facebook,linkedin,copy,invite'],
        ]);

        $hash = hash('sha256', ($request->ip() ?? '').'|'.($request->userAgent() ?? ''));
        $today = \Carbon\Carbon::today();

        $alreadyShared = MemorialShare::where('memorial_id', $memorial->id)
            ->where('visitor_hash', $hash)
            ->where('share_type', $validated['share_type'])
            ->where('shared_at', '>=', $today)
            ->exists();

        if (! $alreadyShared) {
            MemorialShare::create([
                'memorial_id' => $memorial->id,
                'visitor_hash' => $hash,
                'share_type' => $validated['share_type'],
                'shared_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'stats' => MemorialStatsHelper::get($memorial),
        ]);
    }

    /**
     * Return current view & share stats for a memorial (public, no auth).
     */
    public function stats(string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canRead($memorial)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json(MemorialStatsHelper::get($memorial));
    }

    private function formatPost(Post $post): array
    {
        $media = $post->relationLoaded('media') ? $post->media : $post->media;

        return [
            'id' => $post->id,
            'share_id' => $post->share_id,
            'type' => $post->type,
            'tribute_type' => $post->tribute_type,
            'marker_verb' => $post->markerVerb(),
            'title' => $post->title,
            'content' => $post->content,
            'location' => $post->location,
            'chapter' => $post->storyChapter?->title,
            'author' => $post->user?->name ?? $post->memorial->full_name,
            'author_photo' => $post->user?->profile_photo_url,
            'created_at' => $post->created_at->format('F j'),
            'created_at_human' => $post->created_at->diffForHumans(),
            'reaction_count' => (int) ($post->reactions_count ?? $post->reactions()->count()),
            'media' => $media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => \App\Helpers\StorageHelper::publicUrl($m->path),
                'caption' => $m->caption,
            ])->toArray(),
        ];
    }
}
