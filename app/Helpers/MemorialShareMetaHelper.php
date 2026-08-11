<?php

namespace App\Helpers;

use App\Models\Media;
use App\Models\Memorial;
use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Open Graph / Twitter Card data for memorial pages (WhatsApp, Facebook, LinkedIn, etc.).
 */
class MemorialShareMetaHelper
{
    private const DESCRIPTION_LIMIT = 320;

    private const TITLE_LIMIT = 100;

    /**
     * @return array{title: string, description: string, url: string, site_name: string, image?: string|null, image_alt?: string|null}
     */
    public static function forMemorial(Memorial $memorial): array
    {
        $deceasedName = trim((string) ($memorial->full_name ?? '')) ?: 'Our loved one';
        $years = $memorial->birth_death_years;
        $title = 'In loving memory of '.$deceasedName;
        if ($years) {
            $title .= ' ('.$years.')';
        }
        $title = Str::limit($title, self::TITLE_LIMIT, '…');

        $canonical = $memorial->publicUrl();

        return [
            'title' => $title,
            'description' => self::memorialDescription($memorial, $deceasedName),
            'url' => $canonical,
            'site_name' => (string) config('app.name', 'Forever-Loved'),
            // A memorial page is a page about a person, and crawlers treat 'profile'
            // exactly like 'website' when they don't care about the difference.
            'type' => 'profile',
            'image_alt' => $deceasedName,
            ...self::shareImage($memorial->profile_photo_path, $memorial->profile_photo_url),
        ];
    }

    /**
     * @return array{title: string, description: string, url: string, site_name: string, image?: string|null, image_alt?: string|null}
     */
    public static function forChapter(Memorial $memorial, Post $post): array
    {
        $deceasedName = trim((string) ($memorial->full_name ?? '')) ?: 'Loved one';
        $postTitle = trim((string) ($post->title ?? '')) ?: 'Life chapter';
        $title = Str::limit($postTitle.' · '.$deceasedName, self::TITLE_LIMIT, '…');

        $description = self::plainTextFromHtml($post->content ?? '');
        if ($description === '') {
            $description = self::memorialDescription($memorial, $deceasedName);
        } else {
            $description = Str::limit($description, self::DESCRIPTION_LIMIT, '…');
        }

        $shareUrl = route('memorial.chapter.public', [
            'memorial_slug' => $memorial->slug,
            'share_id' => $post->share_id,
        ], true);

        [$imagePath, $imageUrl] = self::firstPostShareImage($post)
            ?? [$memorial->profile_photo_path, $memorial->profile_photo_url];

        return [
            'title' => $title,
            'description' => $description,
            'url' => $shareUrl,
            'site_name' => (string) config('app.name', 'Forever-Loved'),
            'type' => 'article',
            'image_alt' => Str::limit($postTitle.' — '.$deceasedName, 200, '…'),
            ...self::shareImage($imagePath, $imageUrl),
        ];
    }

    /**
     * The og:image entry: the crawler-sized derivative with its dimensions declared,
     * or the original as a fallback when one can't be made. Declared dimensions matter
     * on their own — WhatsApp and Facebook render the card immediately instead of
     * first downloading the image to measure it.
     *
     * @return array{image: string|null, image_width?: int, image_height?: int, image_type?: string}
     */
    private static function shareImage(?string $sourcePath, ?string $fallbackUrl): array
    {
        $derived = app(\App\Services\ShareImageService::class)->derive($sourcePath);

        if ($derived === null) {
            return ['image' => self::absoluteAssetUrl($fallbackUrl)];
        }

        return [
            'image' => $derived['url'],
            'image_width' => $derived['width'],
            'image_height' => $derived['height'],
            'image_type' => $derived['type'],
        ];
    }

    public static function absoluteAssetUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $url = trim($url);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return url($url);
    }

    /** @return array{0: string|null, 1: string|null}|null [disk path, public URL] of the post's first photo. */
    private static function firstPostShareImage(Post $post): ?array
    {
        foreach ($post->media as $medium) {
            $mime = (string) ($medium->mime_type ?? '');
            if ($medium->type === Media::TYPE_PHOTO || ($mime !== '' && str_starts_with($mime, 'image/'))) {
                return [$medium->path, $medium->url];
            }
        }

        return null;
    }

    private static function memorialDescription(Memorial $memorial, string $deceasedName): string
    {
        $fromBio = self::plainTextFromHtml($memorial->biography ?? '');
        if ($fromBio !== '') {
            return Str::limit($fromBio, self::DESCRIPTION_LIMIT, '…');
        }

        if ($memorial->short_description) {
            $t = self::plainTextFromHtml($memorial->short_description);
            if ($t !== '') {
                return Str::limit($t, self::DESCRIPTION_LIMIT, '…');
            }
        }

        $bits = array_values(array_filter(array_unique(array_map(
            fn ($s) => self::plainTextFromHtml((string) $s),
            array_filter([
                $memorial->notable_title,
                $memorial->primary_profession,
                $memorial->known_for,
            ])
        ))));

        $joined = implode('. ', $bits);
        if ($joined !== '') {
            return Str::limit($joined, self::DESCRIPTION_LIMIT, '…');
        }

        return 'Remembering '.$deceasedName.'. Visit this memorial to read their story and leave a tribute.';
    }

    private static function plainTextFromHtml(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        // A space before every tag, so text in adjacent elements keeps a boundary when
        // the tags go. Without it a biography with a facts table stripped down to
        // "…victim of crime.BornMarch 24" — words fused across cells, in the one string
        // Google shows under the page title.
        $text = strip_tags(str_replace('<', ' <', $html));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text;
    }
}
