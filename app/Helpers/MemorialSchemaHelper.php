<?php

namespace App\Helpers;

use App\Models\Memorial;
use Illuminate\Support\Str;

/**
 * JSON-LD Person schema from memorial database fields only (not rendered page HTML).
 */
class MemorialSchemaHelper
{
    /**
     * @return array<string, mixed>|null
     */
    public static function personJsonLd(Memorial $memorial): ?array
    {
        $name = trim((string) ($memorial->full_name ?? ''));
        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                $memorial->first_name,
                $memorial->middle_name,
                $memorial->last_name,
            ])));
        }
        if ($name === '') {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $name,
        ];

        if ($memorial->first_name) {
            $data['givenName'] = $memorial->first_name;
        }
        if ($memorial->last_name) {
            $data['familyName'] = $memorial->last_name;
        }
        if ($memorial->middle_name) {
            $data['additionalName'] = $memorial->middle_name;
        }

        if ($memorial->date_of_birth) {
            $data['birthDate'] = $memorial->date_of_birth->format('Y-m-d');
        }
        if ($memorial->date_of_passing) {
            $data['deathDate'] = $memorial->date_of_passing->format('Y-m-d');
        }

        $birthPlace = self::placeFrom(
            (string) ($memorial->birth_city ?? ''),
            (string) ($memorial->birth_state ?? ''),
            (string) ($memorial->birth_country ?? '')
        );
        if ($birthPlace !== null) {
            $data['birthPlace'] = $birthPlace;
        }

        $deathPlace = self::placeFrom(
            (string) ($memorial->death_city ?? ''),
            (string) ($memorial->death_state ?? ''),
            (string) ($memorial->death_country ?? '')
        );
        if ($deathPlace !== null) {
            $data['deathPlace'] = $deathPlace;
        }

        if ($memorial->gender === 'male') {
            $data['gender'] = 'Male';
        } elseif ($memorial->gender === 'female') {
            $data['gender'] = 'Female';
        }

        if ($memorial->nationality) {
            $data['nationality'] = $memorial->nationality;
        }

        $description = self::factualDescription($memorial);
        if ($description !== '') {
            $data['description'] = $description;
        }

        $imageUrl = $memorial->profile_photo_url;
        if ($imageUrl) {
            $data['image'] = $imageUrl;
        }

        $data['url'] = route('memorial.public', ['slug' => $memorial->slug], true);

        return self::dropEmpty($data);
    }

    /**
     * @return array{@type: string, name: string}|null
     */
    private static function placeFrom(string $city, string $state, string $country): ?array
    {
        $parts = array_values(array_filter(array_map('trim', [$city, $state, $country])));
        if ($parts === []) {
            return null;
        }

        return [
            '@type' => 'Place',
            'name' => implode(', ', $parts),
        ];
    }

    private static function factualDescription(Memorial $memorial): string
    {
        $bits = [];

        if ($memorial->short_description) {
            $bits[] = trim(strip_tags($memorial->short_description));
        }
        if ($memorial->notable_title) {
            $bits[] = trim(strip_tags($memorial->notable_title));
        }
        if ($memorial->primary_profession) {
            $bits[] = trim(strip_tags($memorial->primary_profession));
        }
        if ($memorial->known_for) {
            $bits[] = trim(strip_tags($memorial->known_for));
        }
        if ($memorial->major_achievements) {
            $plain = preg_replace('/\s+/', ' ', trim(strip_tags($memorial->major_achievements))) ?? '';
            if ($plain !== '') {
                $bits[] = Str::limit($plain, 500, '…');
            }
        }

        $bits = array_values(array_filter(array_unique($bits)));

        return implode('. ', $bits);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function dropEmpty(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_array($v)) {
                $nested = self::dropEmpty($v);
                if ($nested !== []) {
                    $out[$k] = $nested;
                }
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
