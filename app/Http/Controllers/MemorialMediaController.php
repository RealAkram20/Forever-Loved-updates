<?php

namespace App\Http\Controllers;

use App\Helpers\HtmlHelper;
use App\Helpers\StorageHelper;
use App\Models\Media;
use App\Models\Memorial;
use App\Models\Post;
use App\Models\StoryChapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemorialMediaController extends Controller
{
    private const MAX_VIDEO_SIZE = 100 * 1024 * 1024; // 100MB
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

        $request->validate(['photo' => ['required', 'image', 'max:5120']]); // 5MB

        $path = $request->file('photo')->store(StorageHelper::memorialProfilePath($memorial->id), 'public');
        $memorial->update(['profile_photo_path' => $path]);

        return response()->json([
            'success' => true,
            'url' => StorageHelper::publicUrl($path),
        ]);
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

        $request->validate([
            'file' => ['required', 'file'],
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
        } else {
            return response()->json(['error' => 'Invalid file type. Use images or videos (mp4, webm).'], 422);
        }

        $path = $file->store(StorageHelper::memorialGalleryPath($memorial->id), 'public');

        $media = Media::create([
            'memorial_id' => $memorial->id,
            'user_id' => $request->user()?->id,
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
            'story_chapter_id' => ['nullable', 'integer', 'exists:story_chapters,id'],
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => ['integer', 'exists:media,id'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:102400'], // 100MB
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email'],
        ]);

        $userId = $request->user()?->id;
        $guestName = $validated['guest_name'] ?? null;
        $guestEmail = $validated['guest_email'] ?? null;

        if (!$userId && (!$guestName || !$guestEmail)) {
            return response()->json(['error' => 'Name and email are required to add your chapter'], 422);
        }

        if (!$userId && $guestEmail) {
            $existingUser = \App\Models\User::where('email', strtolower($guestEmail))->first();
            if ($existingUser) {
                $userId = $existingUser->id;
            } else {
                $user = \App\Models\User::create([
                    'name' => $guestName,
                    'email' => strtolower($guestEmail),
                    'password' => null,
                ]);
                $userId = $user->id;
            }
        }

        $post = $memorial->posts()->create([
            'user_id' => $userId,
            'story_chapter_id' => $validated['story_chapter_id'] ?? null,
            'type' => 'gallery',
            'title' => $validated['title'] ?? null,
            'content' => HtmlHelper::sanitize($validated['content'] ?? null),
        ]);

        $mediaIds = $validated['media_ids'] ?? [];
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
                if ($type && $file->getSize() <= self::MAX_VIDEO_SIZE) {
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

        return response()->json([
            'success' => true,
            'post' => $this->formatPost($post),
        ]);
    }

    private function canEdit(Memorial $memorial): bool
    {
        $user = auth()->user();
        return $user && ($memorial->user_id === $user->id || $user->hasRole(['admin', 'super-admin']));
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
            'title' => $post->title,
            'content' => $post->content,
            'author' => $post->user?->name ?? $post->memorial->full_name,
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
