<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlHelper;
use App\Helpers\PlanLimitsHelper;
use App\Helpers\StorageHelper;
use App\Models\Media;
use App\Models\Memorial;
use App\Models\Post;
use App\Models\StoryChapter;
use App\Models\Tribute;
use App\Services\NotificationService;
use App\Support\GuestIdentity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemorialMediaController extends Controller
{
    private const MAX_VIDEO_SIZE = 100 * 1024 * 1024; // 100MB
    /** Per-request cap on the guest chapter endpoint, which is reachable without signing in. */
    private const MAX_TRIBUTE_POST_FILES = 10;
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];
    private const ALLOWED_AUDIO_MIMES = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm'];

    /**
     * Upload profile photo. Admin or owner only.
     */
    public function uploadProfilePhoto(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $request->validate(['photo' => ['required', 'image', 'max:5120']]); // 5MB

        $photo = $request->file('photo');
        $path = $photo->store(StorageHelper::memorialProfilePath($memorial->id), 'public');

        // Recorded because a profile photo never gets a media row, so it is invisible to
        // any usage sum that only looks at the media table.
        $memorial->update([
            'profile_photo_path' => $path,
            'profile_photo_size' => $photo->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'url' => StorageHelper::publicUrl($path),
        ]);
    }

    /**
     * Upload the cover banner shown behind the profile photo. Admin or owner only.
     *
     * Deliberately not plan-gated: the banner is part of how a memorial presents itself
     * rather than a paid extra, so every plan can set one. It still passes through the
     * same canModifyMedia() check as the profile photo, which is what stops uploads to a
     * memorial whose subscription has lapsed.
     */
    public function uploadCoverPhoto(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        // Wider than the profile photo allowance: a banner is a landscape crop that stays
        // sharp across a full-width card, so 5MB would force visible compression.
        $request->validate(['photo' => ['required', 'image', 'max:8192']]); // 8MB

        $photo = $request->file('photo');
        $path = $photo->store(StorageHelper::memorialCoverPath($memorial->id), 'public');

        $previous = $memorial->cover_photo_path;

        $memorial->update([
            'cover_photo_path' => $path,
            'cover_photo_size' => $photo->getSize(),
        ]);

        // Replacing a cover would otherwise leave the old file on disk, counted against
        // nothing and reachable by anyone who kept the URL.
        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return response()->json([
            'success' => true,
            'url' => StorageHelper::publicUrl($path),
        ]);
    }

    /**
     * Remove the cover banner, falling the card back to its default header. Admin or owner only.
     */
    public function removeCoverPhoto(string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        if ($memorial->cover_photo_path) {
            Storage::disk('public')->delete($memorial->cover_photo_path);
            $memorial->update([
                'cover_photo_path' => null,
                'cover_photo_size' => null,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Upload gallery media (images or videos <100MB). Admin or owner only.
     */
    public function uploadGalleryMedia(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canUpload($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $request->validate([
            'file' => ['required', 'file'],
            'caption' => ['nullable', 'string', 'max:255'],
            'gallery_category_id' => ['nullable', 'integer'],
        ]);

        $categoryId = $this->resolveCategoryId($memorial, $request->input('gallery_category_id'));

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $size = $file->getSize();

        $type = null;
        if (in_array($mime, self::ALLOWED_IMAGE_MIMES)) {
            $type = 'photo';
        } elseif (in_array($mime, self::ALLOWED_VIDEO_MIMES)) {
            if ($size > self::MAX_VIDEO_SIZE) {
                return response()->json(['error' => 'Video must be less than 100MB'], 422);
            }
            $type = 'video';
        } else {
            return response()->json(['error' => 'Invalid file type. Use images or videos (mp4, webm).'], 422);
        }

        $limitCheck = $type === 'photo'
            ? PlanLimitsHelper::canUploadGalleryImage($memorial)
            : PlanLimitsHelper::canUploadGalleryVideo($memorial);

        if (!$limitCheck['allowed']) {
            $label = $type === 'photo' ? 'image' : 'video';
            return response()->json([
                'error' => $limitCheck['reason']
                    ?? "Gallery {$label} limit reached ({$limitCheck['current']}/{$limitCheck['max']}). Upgrade your plan for more.",
            ], 422);
        }

        // The plan's storage allowances were validated on the admin form and written to the
        // database, and then never read by anything — a plan saying 100MB could hold ten
        // gigabytes. Video carries its own budget on top, because that is how the video
        // allowance is sold.
        $storageCheck = PlanLimitsHelper::canStore($memorial, $size, $type);
        if (!$storageCheck['allowed']) {
            return response()->json(['error' => $storageCheck['reason']], 422);
        }

        $path = $file->store(StorageHelper::memorialGalleryPath($memorial->id), 'public');

        $media = Media::create([
            'memorial_id' => $memorial->id,
            'user_id' => $request->user()?->id,
            'gallery_category_id' => $categoryId,
            'type' => $type,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $size,
            'caption' => $request->input('caption'),
            'sort_order' => $memorial->media()->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'media' => [
                'id' => $media->id,
                'type' => $media->type,
                'url' => StorageHelper::publicUrl($path),
                'caption' => $media->caption,
                'gallery_category_id' => $media->gallery_category_id,
            ],
        ]);
    }

    /**
     * Upload media for a post/story (text, images, audio, video). Admin or owner only.
     */
    public function uploadPostMedia(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $request->validate([
            'file' => ['required', 'file'],
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $size = $file->getSize();

        $type = null;
        if (in_array($mime, self::ALLOWED_IMAGE_MIMES)) {
            $type = 'photo';
        } elseif (in_array($mime, self::ALLOWED_VIDEO_MIMES)) {
            if ($size > self::MAX_VIDEO_SIZE) {
                return response()->json(['error' => 'Video must be less than 100MB'], 422);
            }
            $type = 'video';
        } elseif (in_array($mime, self::ALLOWED_AUDIO_MIMES)) {
            $type = 'music';
        } else {
            return response()->json(['error' => 'Invalid file type.'], 422);
        }

        $path = $file->store(StorageHelper::memorialPostsPath($memorial->id), 'public');

        $media = Media::create([
            'memorial_id' => $memorial->id,
            'user_id' => $request->user()?->id,
            'type' => $type,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $size,
            'caption' => $request->input('caption'),
        ]);

        $postId = $request->input('post_id');
        if ($postId) {
            $post = $memorial->posts()->find($postId);
            if ($post) {
                $post->media()->attach($media->id, ['sort_order' => $post->media()->count()]);
            }
        }

        return response()->json([
            'success' => true,
            'media' => [
                'id' => $media->id,
                'type' => $media->type,
                'url' => StorageHelper::publicUrl($path),
                'caption' => $media->caption,
            ],
        ]);
    }

    /**
     * Create a tribute post (story). Admin, owner, or contributors.
     * Accepts multipart with optional files (images, video, audio).
     */
    public function storeTributePost(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (!$memorial->is_public) {
            return response()->json(['error' => 'Memorial is not public'], 404);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:50000'],
            // What the author said this was: a flower, a candle, a prayer — or nothing,
            // which is the common case and means a plain story.
            'tribute_type' => ['nullable', Rule::in(Tribute::TYPES)],
            'story_chapter_id' => ['nullable', 'integer', 'exists:story_chapters,id'],
            'media_ids' => ['nullable', 'array', 'max:'.self::MAX_TRIBUTE_POST_FILES],
            'media_ids.*' => ['integer', 'exists:media,id'],
            // Bounded. This endpoint takes no authentication, and the array had no size
            // limit — one request could carry an unlimited number of 100MB files, which is
            // a storage bill and a full disk rather than an attack anyone needs skill for.
            'files' => ['nullable', 'array', 'max:'.self::MAX_TRIBUTE_POST_FILES],
            'files.*' => ['file', 'max:102400'], // 100MB
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $tributeCheck = PlanLimitsHelper::canAddTribute($memorial);
        if (!$tributeCheck['allowed']) {
            return response()->json([
                'error' => "Tribute limit reached ({$tributeCheck['current']}/{$tributeCheck['max']}). Upgrade your plan for more.",
            ], 422);
        }

        $idempotencyKey = $validated['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $cacheKey = 'tribute_post:' . $memorial->id . ':' . $idempotencyKey;
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        $userId = $request->user()?->id;
        $guestName = $validated['guest_name'] ?? null;
        $guestEmail = $validated['guest_email'] ?? null;

        if (!$userId && (!$guestName || !$guestEmail)) {
            return response()->json(['error' => 'Name and email are required to add your chapter'], 422);
        }

        // A registered address is its owner's; adopting the match published this chapter,
        // and any media attached to it, under that member's name.
        if (!$userId && GuestIdentity::isRegistered($guestEmail)) {
            return GuestIdentity::requiresLoginResponse('add your chapter');
        }

        if (!$userId && $guestEmail) {
            $user = \App\Models\User::create([
                'name' => $guestName,
                'email' => strtolower($guestEmail),
                'password' => null,
            ]);
            $userId = $user->id;
        }

        // Scoped to THIS memorial before anything is attached or linked. The validation rules
        // say only `exists:media,id` / `exists:story_chapters,id` — any row in the table — and
        // this endpoint takes no authentication, so an attacker could enumerate ids and attach
        // a *private* memorial's photos (or another reseller's) to a post on a public one and
        // then simply read them off the page.
        $mediaIds = $memorial->media()
            ->whereIn('id', $validated['media_ids'] ?? [])
            ->pluck('id')
            ->all();

        $chapterId = $validated['story_chapter_id'] ?? null;
        if ($chapterId && ! $memorial->storyChapters()->whereKey($chapterId)->exists()) {
            $chapterId = null;
        }

        $post = $memorial->posts()->create([
            'user_id' => $userId,
            'story_chapter_id' => $chapterId,
            'type' => 'gallery',
            'tribute_type' => $validated['tribute_type'] ?? null,
            'title' => $validated['title'] ?? null,
            'content' => HtmlHelper::sanitize($validated['content'] ?? null),
        ]);

        $sortOrder = 0;

        // Upload new files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $mime = $file->getMimeType();
                $type = null;
                if (in_array($mime, self::ALLOWED_IMAGE_MIMES)) {
                    $type = 'photo';
                } elseif (in_array($mime, self::ALLOWED_VIDEO_MIMES)) {
                    $type = 'video';
                } elseif (in_array($mime, self::ALLOWED_AUDIO_MIMES)) {
                    $type = 'music';
                }
                // Re-checked per file rather than once for the batch: ten files are attached
                // in a loop, and the memorial's usage grows with each one.
                $withinAllowance = $type
                    && PlanLimitsHelper::canStore($memorial, (int) $file->getSize(), $type)['allowed'];

                if ($withinAllowance && $file->getSize() <= self::MAX_VIDEO_SIZE) {
                    $path = $file->store(StorageHelper::memorialPostsPath($memorial->id), 'public');
                    $media = Media::create([
                        'memorial_id' => $memorial->id,
                        'user_id' => $userId,
                        'type' => $type,
                        'path' => $path,
                        'filename' => $file->getClientOriginalName(),
                        'mime_type' => $mime,
                        'size' => $file->getSize(),
                    ]);
                    $post->media()->attach($media->id, ['sort_order' => $sortOrder++]);
                }
            }
        }

        foreach ($mediaIds as $mediaId) {
            $post->media()->attach($mediaId, ['sort_order' => $sortOrder++]);
        }

        $post->load('media', 'user');

        // Titles are optional on a story, so the marker stands in when there is none —
        // "lit a candle" tells a subscriber more than "A story" does.
        $chapterTitle = $post->title
            ?: ($post->markerVerb() ? ucfirst($post->markerVerb()) : ($post->storyChapter?->title ?? 'A story'));
        $authorName = $post->user?->name ?? $guestName ?? 'A guest';
        NotificationService::notifyNewLifeChapter($memorial, $chapterTitle, $userId, $post, $authorName);

        $response = [
            'success' => true,
            'post' => $this->formatPost($post),
        ];
        if ($idempotencyKey) {
            Cache::put('tribute_post:' . $memorial->id . ':' . $idempotencyKey, $response, now()->addMinutes(5));
        }

        return response()->json($response);
    }

    /**
     * Upload or replace background music. Admin or owner only.
     */
    public function uploadBackgroundMusic(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        if (!PlanLimitsHelper::canUseBackgroundMusic($memorial)) {
            return response()->json(['error' => 'Background music is not available on your current plan. Upgrade for this feature.'], 422);
        }

        $request->validate(['file' => ['required', 'file', 'max:20480']]); // 20MB

        $file = $request->file('file');
        $mime = $file->getMimeType();

        if (!in_array($mime, self::ALLOWED_AUDIO_MIMES)) {
            return response()->json(['error' => 'Invalid file type. Use MP3, WAV, or OGG audio.'], 422);
        }

        if ($memorial->background_music) {
            Storage::disk('public')->delete($memorial->background_music);
        }

        $path = $file->store("memorials/{$memorial->id}/background", 'public');
        $memorial->update(['background_music' => $path]);

        return response()->json([
            'success' => true,
            'url' => StorageHelper::publicUrl($path),
        ]);
    }

    /**
     * Remove background music. Admin or owner only.
     */
    public function removeBackgroundMusic(string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        if ($memorial->background_music) {
            Storage::disk('public')->delete($memorial->background_music);
            $memorial->update(['background_music' => null]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Update a gallery item's caption and the category it is filed under. Admin, owner, or
     * collaborator only.
     */
    public function updateGalleryMedia(Request $request, string $slug, int $mediaId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $media = $memorial->media()->findOrFail($mediaId);

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'gallery_category_id' => ['nullable', 'integer'],
        ]);

        $changes = ['caption' => $validated['caption'] ?? null];

        // array_key_exists, not ?? — an explicit null means "un-file this", while omitting
        // the key entirely means "I was only editing the caption". Collapsing the two would
        // make every caption edit quietly empty the category.
        if (array_key_exists('gallery_category_id', $validated)) {
            $changes['gallery_category_id'] = $this->resolveCategoryId($memorial, $validated['gallery_category_id']);
        }

        $media->update($changes);

        return response()->json([
            'success' => true,
            'media' => [
                'id' => $media->id,
                'caption' => $media->caption,
                'gallery_category_id' => $media->gallery_category_id,
            ],
        ]);
    }

    /**
     * Turn a category id off the wire into one this memorial actually owns, or null.
     *
     * Re-resolved through the memorial rather than trusted after an `exists:` rule, the same
     * way storeTributePost() re-scopes media_ids and story_chapter_id: `exists` only proves
     * the row is somewhere in the table, so on its own it would let a curator file their
     * photo into a stranger's category and have it surface on that memorial's page.
     */
    private function resolveCategoryId(Memorial $memorial, mixed $categoryId): ?int
    {
        if (! $categoryId) {
            return null;
        }

        return $memorial->galleryCategories()->whereKey($categoryId)->value('id');
    }

    /**
     * Delete gallery media. Admin, owner, or collaborator only.
     */
    public function deleteGalleryMedia(string $slug, int $mediaId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (!$this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mediaCheck = PlanLimitsHelper::canModifyMedia($memorial);
        if (!$mediaCheck['allowed']) {
            return response()->json(['error' => $mediaCheck['reason']], 403);
        }

        $media = $memorial->media()->find($mediaId);
        if (!$media) {
            return response()->json(['success' => true, 'type' => 'unknown']);
        }

        // A picture a story is carrying is not the gallery's to destroy. It appears here
        // under "From Stories" so that visitors can browse it, and deleting the file from
        // under the story would leave a broken image in the middle of what someone wrote.
        // Removing it means removing it from the story.
        if ($media->posts()->exists()) {
            return response()->json([
                'error' => 'This is part of a story. Delete it from the story it belongs to.',
            ], 422);
        }

        $type = $media->type;

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return response()->json(['success' => true, 'type' => $type]);
    }

    private function canEdit(Memorial $memorial): bool
    {
        return $memorial->canBeEditedBy(auth()->user());
    }

    private function canUpload(Memorial $memorial): bool
    {
        return $this->canEdit($memorial);
    }

    private function formatPost(Post $post): array
    {
        return [
            'id' => $post->id,
            'share_id' => $post->share_id,
            'type' => $post->type,
            'tribute_type' => $post->tribute_type,
            'marker_verb' => $post->markerVerb(),
            'title' => $post->title,
            'content' => $post->content,
            'author' => $post->user?->name ?? $post->memorial->full_name,
            'author_photo' => $post->user?->profile_photo_url,
            'created_at' => $post->created_at->diffForHumans(),
            'created_at_iso' => $post->created_at->toIso8601String(),
            'reaction_count' => $post->reactions()->count(),
            'media' => $post->media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => StorageHelper::publicUrl($m->path),
                'caption' => $m->caption,
            ])->toArray(),
        ];
    }
}
