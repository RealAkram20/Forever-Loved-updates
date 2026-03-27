<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlHelper;
use App\Helpers\MemorialStatsHelper;
use App\Helpers\PlanLimitsHelper;
use App\Models\Comment;
use App\Models\Memorial;
use App\Models\MemorialShare;
use App\Models\MemorialSubscription;
use App\Models\TributeComment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\StoryChapter;
use App\Models\Tribute;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SystemMailConfigurator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MemorialApiController extends Controller
{
    /**
     * Update a memorial section (inline edit). Admin or owner only.
     */
    public function updateSection(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'section' => ['required', 'string', 'in:full_name,designation,biography,date_of_birth,date_of_passing,dates'],
            'value' => ['nullable', 'string', 'max:50000'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
        ]);

        $section = $validated['section'];
        $value = $validated['value'] ?? '';

        if ($section === 'full_name') {
            $memorial->update(['full_name' => trim($value)]);
        } elseif ($section === 'designation') {
            $memorial->update(['designation' => trim($value) ?: null]);
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
     * Store a tribute (flower, candle, note). Guest or authenticated.
     */
    public function storeTribute(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (!$memorial->is_public) {
            return response()->json(['error' => 'Memorial is not public'], 404);
        }

        $userId = $request->user()?->id;
        if ($userId) {
            $request->merge(['user_id' => $userId]);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:flower,candle,note'],
            'message' => ['nullable', 'string', 'max:10000'],
            'guest_name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'guest_email' => ['required_without:user_id', 'nullable', 'email'],
        ]);

        $guestName = $request->user()?->name ?? $validated['guest_name'] ?? null;
        $guestEmail = $request->user()?->email ?? $validated['guest_email'] ?? null;

        if (!$userId && (!$guestName || !$guestEmail)) {
            return response()->json(['error' => 'Name and email are required for guests'], 422);
        }

        // If guest: use existing user's name when email exists, else create new user
        if (!$userId && $guestEmail) {
            $existingUser = User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                $userId = $existingUser->id;
                $guestName = $existingUser->name;
            } else {
                $user = User::create([
                    'name' => $guestName,
                    'email' => strtolower($guestEmail),
                    'password' => null,
                ]);

                NotificationService::notifyNewUserSignup($user);

                try {
                    SystemMailConfigurator::applyFromSettings();
                    if (SystemMailConfigurator::mailDeliveryConfigured()) {
                        $setupUrl = route('password.request');
                        Mail::raw(
                            "Welcome to Forever-Loved!\n\nYou've left a tribute. To complete your account and set a password, visit: {$setupUrl}\n\nYou can also sign in with a one-time code at: " . route('login.passwordless'),
                            function ($message) use ($guestEmail) {
                                $message->to($guestEmail)->subject('Welcome to Forever-Loved - Complete your account');
                            }
                        );
                    }
                } catch (\Exception $e) {
                    report($e);
                }

                $userId = $user->id;
                $guestName = $user->name;
            }
        }

        $tribute = Tribute::create([
            'memorial_id' => $memorial->id,
            'user_id' => $userId,
            'type' => $validated['type'],
            'message' => HtmlHelper::sanitize($validated['message'] ?? null),
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'is_approved' => true,
        ]);

        $tribute->load('user');

        $authorName = $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous';
        NotificationService::notifyNewTribute($memorial, $validated['type'], $authorName, $userId, $tribute);

        return response()->json([
            'success' => true,
            'tribute' => [
                'id' => $tribute->id,
                'share_id' => $tribute->share_id,
                'type' => $tribute->type,
                'message' => $tribute->message,
                'author' => $authorName,
                'author_photo' => $tribute->user?->profile_photo_url,
                'created_at' => $tribute->created_at->diffForHumans(),
                'created_at_iso' => $tribute->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Store a reaction (like, love, candle, flower) on a post or tribute.
     */
    public function storeReaction(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (!$memorial->is_public) {
            return response()->json(['error' => 'Memorial is not public'], 404);
        }

        $validated = $request->validate([
            'reactionable_type' => ['required', 'in:post,tribute'],
            'reactionable_id' => ['required', 'integer'],
            'type' => ['required', 'in:like,love,candle,flower'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email'],
        ]);

        $userId = $request->user()?->id;
        $guestName = $validated['guest_name'] ?? null;
        $guestEmail = $validated['guest_email'] ?? null;

        if (!$userId && (!$guestName || !$guestEmail)) {
            return response()->json([
                'error' => 'Name and email required',
                'requires_guest_info' => true,
            ], 422);
        }

        // If guest with existing account, ask them to log in
        if (!$userId && $guestEmail) {
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

            try {
                SystemMailConfigurator::applyFromSettings();
                if (SystemMailConfigurator::mailDeliveryConfigured()) {
                    Mail::raw(
                        "Welcome to Forever-Loved! You can sign in with a one-time code at: " . route('login.passwordless'),
                        function ($message) use ($guestEmail) {
                            $message->to($guestEmail)->subject('Welcome to Forever-Loved');
                        }
                    );
                }
            } catch (\Exception $e) {
                report($e);
            }

            $userId = $user->id;
        }

        $modelClass = $validated['reactionable_type'] === 'post' ? Post::class : Tribute::class;
        $reactionable = $modelClass::where('memorial_id', $memorial->id)->findOrFail($validated['reactionable_id']);

        $existing = Reaction::where('reactionable_type', $modelClass)
            ->where('reactionable_id', $reactionable->id)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn ($q) => $q->where('guest_email', $guestEmail))
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
        $chapters = $memorial->storyChapters()->orderBy('sort_order')->get();
        return response()->json(['chapters' => $chapters]);
    }

    /**
     * Store a story chapter. Admin or owner only.
     */
    public function storeChapter(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $chapterCheck = PlanLimitsHelper::canAddChapter($memorial);
        if (!$chapterCheck['allowed']) {
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
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
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
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
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

        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
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
            'content' => $validated['content'] ?? null,
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

        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

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

        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $post->delete();

        return response()->json(['success' => true]);
    }

    /**
     * List tributes for memorial.
     */
    public function tributes(string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

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

        if (!$this->canEditTribute($memorial, $tribute)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'in:flower,candle,note'],
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

        if (!$this->canEditTribute($memorial, $tribute)) {
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
        if (!$user) {
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

        if (!PlanLimitsHelper::canUseGuestNotifications($memorial)) {
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

        if (!$userId && $guestEmail) {
            $existingUser = User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                $userId = $existingUser->id;
                $guestName = $existingUser->name;
                $guestEmail = null;
            }
        }

        if (!$userId && !$guestEmail) {
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

        if (!$userId && $guestEmail) {
            $existingUser = User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                $userId = $existingUser->id;
                $guestEmail = null;
            }
        }

        $sub = $userId
            ? MemorialSubscription::where('memorial_id', $memorial->id)->where('user_id', $userId)->first()
            : MemorialSubscription::where('memorial_id', $memorial->id)->where('guest_email', strtolower($guestEmail))->first();

        if (!$sub) {
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

        if (!$userId && $guestEmail) {
            $existingUser = User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                $userId = $existingUser->id;
                $guestEmail = null;
            }
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

        if (!$userId && $guestEmail) {
            $existingUser = User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                $userId = $existingUser->id;
                $guestEmail = null;
            }
        }

        $sub = $userId
            ? MemorialSubscription::where('memorial_id', $memorial->id)->where('user_id', $userId)->first()
            : ($guestEmail ? MemorialSubscription::where('memorial_id', $memorial->id)->where('guest_email', strtolower($guestEmail))->first() : null);

        if (!$sub) {
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
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $comment = Comment::where('id', $commentId)
            ->whereHas('post', fn ($q) => $q->where('memorial_id', $memorial->id))
            ->first();

        if (!$comment) {
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
     * Get comments for a post.
     */
    public function comments(string $slug, int $postId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$memorial->is_public) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $post = $memorial->posts()->findOrFail($postId);
        $comments = $post->comments()->get()->map(fn ($c) => [
            'id' => $c->id,
            'parent_id' => $c->parent_id,
            'content' => $c->content,
            'author' => $c->author_name,
            'author_photo' => $c->user?->profile_photo_url,
            'created_at' => $c->created_at->diffForHumans(),
            'replies' => $c->replies->map(fn ($r) => [
                'id' => $r->id,
                'parent_id' => $r->parent_id,
                'content' => $r->content,
                'author' => $r->author_name,
                'author_photo' => $r->user?->profile_photo_url,
                'created_at' => $r->created_at->diffForHumans(),
            ])->toArray(),
        ]);
        return response()->json(['comments' => $comments]);
    }

    /**
     * Store a comment on a post. Guest or authenticated.
     */
    public function storeComment(Request $request, string $slug, int $postId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$memorial->is_public) {
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

        if (!$userId && (!$guestName || !$guestEmail)) {
            return response()->json(['error' => 'Name and email are required for guests'], 422);
        }

        if (!$userId && $guestEmail) {
            $existingUser = User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                $userId = $existingUser->id;
                $guestName = $existingUser->name;
            }
        }

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            $parent = Comment::where('post_id', $post->id)->find($parentId);
            if (!$parent) {
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

        return response()->json([
            'success' => true,
            'comment' => [
                'id' => $comment->id,
                'parent_id' => $comment->parent_id,
                'content' => $comment->content,
                'author' => $comment->author_name,
                'author_photo' => $comment->user?->profile_photo_url,
                'created_at' => $comment->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Get reactions for a post (for dropdown).
     */
    public function reactions(string $slug, int $postId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$memorial->is_public) {
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
     * Store a comment on a tribute. Guest or authenticated.
     */
    public function storeTributeComment(Request $request, string $slug, int $tributeId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$memorial->is_public) {
            return response()->json(['error' => 'Memorial is not public'], 404);
        }
        $tribute = $memorial->tributes()->findOrFail($tributeId);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:tribute_comments,id'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email'],
        ]);

        $userId = $request->user()?->id;
        $guestName = $request->user()?->name ?? $validated['guest_name'] ?? null;
        $guestEmail = $request->user()?->email ?? $validated['guest_email'] ?? null;

        if (!$userId && (!$guestName || !$guestEmail)) {
            return response()->json(['error' => 'Name and email are required for guests'], 422);
        }

        if (!$userId && $guestEmail) {
            $existingUser = User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                $userId = $existingUser->id;
                $guestName = $existingUser->name;
            }
        }

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            $parent = TributeComment::where('tribute_id', $tribute->id)->find($parentId);
            if (!$parent) {
                return response()->json(['error' => 'Invalid parent comment'], 422);
            }
        }

        $comment = TributeComment::create([
            'tribute_id' => $tribute->id,
            'parent_id' => $parentId,
            'user_id' => $userId,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'content' => trim($validated['content']),
            'is_approved' => true,
        ]);

        NotificationService::notifyCommentOnTribute($tribute, $comment, $userId);

        $comment->load('user');

        return response()->json([
            'success' => true,
            'comment' => [
                'id' => $comment->id,
                'parent_id' => $comment->parent_id,
                'content' => $comment->content,
                'author' => $comment->author_name,
                'author_photo' => $comment->user?->profile_photo_url,
                'created_at' => $comment->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Record a memorial profile share (deduped: one record per visitor per type per day).
     * Returns updated stats so the frontend can refresh counters immediately.
     */
    public function trackShare(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$memorial->is_public) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if (!PlanLimitsHelper::canShareMemories($memorial)) {
            return response()->json(['error' => 'Sharing is not available on this memorial\'s plan.'], 422);
        }

        $validated = $request->validate([
            'share_type' => ['required', 'in:whatsapp,facebook,linkedin,copy,invite'],
        ]);

        $hash = hash('sha256', ($request->ip() ?? '') . '|' . ($request->userAgent() ?? ''));
        $today = \Carbon\Carbon::today();

        $alreadyShared = MemorialShare::where('memorial_id', $memorial->id)
            ->where('visitor_hash', $hash)
            ->where('share_type', $validated['share_type'])
            ->where('shared_at', '>=', $today)
            ->exists();

        if (!$alreadyShared) {
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
        if (!$memorial->is_public) {
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
