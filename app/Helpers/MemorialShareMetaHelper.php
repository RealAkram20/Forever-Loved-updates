<?php

namespace App\Helpers;

use App\Models\Media;
use App\Models\Memorial;
use App\Models\Post;
use App\Models\Tribute;
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

        $canonical = route('memorial.public', ['slug' => $memorial->slug], true);

        return [
            'title' => $title,
            'description' => self::memorialDescription($memorial, $deceasedName),
            'url' => $canonical,
            'site_name' => (string) config('app.name', 'Forever-Loved'),
            'image' => self::absoluteAssetUrl($memorial->profile_photo_url),
            'image_alt' => $deceasedName,
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

        $image = self::firstPostShareImageUrl($post) ?? self::absoluteAssetUrl($memorial->profile_photo_url);

        return [
            'title' => $title,
            'description' => $description,
            'url' => $shareUrl,
            'site_name' => (string) config('app.name', 'Forever-Loved'),
            'image' => $image,
            'image_alt' => Str::limit($postTitle.' — '.$deceasedName, 200, '…'),
        ];
    }

    /**
     * @return array{title: string, description: string, url: string, site_name: string, image?: string|null, image_alt?: string|null}
     */
    public static function forTribute(Memorial $memorial, Tribute $tribute): array
    {
        $authorName = $tribute->user?->name ?? $tribute->guest_name ?? 'Someone';
        $deceasedName = trim((string) ($memorial->full_name ?? '')) ?: 'Loved one';

        $years = $memorial->birth_death_years;
        $ageSuffix = $years ? ' ('.$years.')' : '';

        $title = Str::limit($authorName.' · Tribute to '.$deceasedName.$ageSuffix, self::TITLE_LIMIT, '…');

        $contentPreview = $tribute->message
            ? Str::limit(self::plainTextFromHtml($tribute->message), 200, '…')
            : 'Left a '.$tribute->type.' in memory of '.$deceasedName;

        $shareUrl = route('memorial.tribute.public', [
            'memorial_slug' => $memorial->slug,
            'share_id' => $tribute->share_id,
        ], true);

        return [
            'title' => $title,
            'description' => $contentPreview,
            'url' => $shareUrl,
            'site_name' => (string) config('app.name', 'Forever-Loved'),
            'image' => self::absoluteAssetUrl($memorial->profile_photo_url),
            'image_alt' => $deceasedName,
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

    private static function firstPostShareImageUrl(Post $post): ?string
    {
        foreach ($post->media as $medium) {
            if ($medium->type === Media::TYPE_PHOTO) {
                return self::absoluteAssetUrl($medium->url);
            }
            $mime = (string) ($medium->mime_type ?? '');
            if ($mime !== '' && str_starts_with($mime, 'image/')) {
                return self::absoluteAssetUrl($medium->url);
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
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text;
    }
}
