<?php

namespace App\Support;

use App\Helpers\ThemeSetting;
use App\Models\Memorial;

/**
 * The words that travel with a memorial link.
 *
 * A bare URL in a WhatsApp thread asks the person who receives it to work out what it is and
 * what they are supposed to do with it. This says both, in the voice a family would use, so the
 * one who forwards it does not have to write the message themselves at the worst moment of their
 * year.
 *
 * Composed here rather than in the page so it can be tested, and so the one line that is a brand
 * line can be resolved against whichever site is being shared from.
 */
class MemorialShareMessage
{
    public static function for(Memorial $memorial): string
    {
        $name = trim((string) $memorial->full_name);
        $years = $memorial->birth_death_years;
        $first = trim((string) \Illuminate\Support\Str::before($name, ' ')) ?: $name;

        // An en dash between the years, as a headstone has it, rather than the hyphen the
        // accessor joins on for the compact listings.
        $headline = $years
            ? "In loving memory of {$name} (".str_replace('-', '–', $years).')'
            : "In loving memory of {$name}";

        $lines = [$headline];

        if ($closing = self::closingLine()) {
            $lines[] = $closing;
        }

        $lines[] = "Please click the link below to share a memory, leave a message, add a photo, or simply light a candle in {$first}'s honour.";
        $lines[] = 'Your memories will help keep '.self::possessive($memorial).' legacy alive.';

        return implode("\n\n", $lines);
    }

    /**
     * "Forever loved, Always 🤎" is our name and our tagline in one line, which is right on our
     * own site and is somebody else's marketing in a grieving family's WhatsApp message on a
     * reseller's. A reseller gets their own line if they have written one, and no line at all if
     * they have not — the message reads perfectly well without it, and an empty space is better
     * than our brand on their family's forward.
     */
    private static function closingLine(): ?string
    {
        if (! ThemeSetting::isResellerSite()) {
            return 'Forever loved, Always 🤎';
        }

        $tagline = ThemeSetting::tenantOwn('branding.tagline');

        return $tagline ? trim($tagline).' 🤎' : null;
    }

    /**
     * his / her / their.
     *
     * `their` is the fallback rather than a guess, because gender is optional on a memorial and
     * getting it wrong in the message a family forwards to everyone who knew them is a worse
     * failure than sounding slightly formal.
     */
    private static function possessive(Memorial $memorial): string
    {
        return match ($memorial->gender) {
            'male' => 'his',
            'female' => 'her',
            default => 'their',
        };
    }
}
