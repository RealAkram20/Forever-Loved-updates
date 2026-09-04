<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A name that a person could plausibly have, rather than a message addressed to somebody.
 *
 * Written on 2026-09-04, during an attack that was using this site as a phishing relay. The
 * shape of it is worth recording, because the defence only makes sense against the attack:
 *
 *   name:  "Your account has been inactive for 364 days. To avoid deletion and claim your
 *           balance, please sign in and request a withdrawal within 24 hours. For support,
 *           join graph.org/UXoRKiiyhc-09-04?auK64r"
 *   email: a stranger's real address
 *
 * The account itself was worthless to the attacker. The point was the verification mail: our
 * server sends it, from our domain, with a passing SPF/DKIM signature, and it opens "Hello
 * <name>". The victim receives well-delivered phishing that we paid to send. Hundreds of those
 * an hour is how a sending domain gets blacklisted, and a blacklisted domain is how a family
 * stops being told that somebody left a tribute on their mother's memorial.
 *
 * **Rate limits do not help here** — every request is a different address and looks ordinary.
 * **A honeypot does not help much either** — it catches the lazy, and this operator will read
 * one page and skip the field. What stops it is that the payload has to travel in the name,
 * and a name is a field we know a great deal about.
 *
 * Deliberately permissive about *people*, strict about *messages*. Names have apostrophes,
 * hyphens, accents, multiple parts, non-Latin scripts; "Ngũgĩ wa Thiong'o" and "Sr. José
 * María Ruiz-Tagle" both pass. What they never contain is a URL, a line break, or a sentence.
 */
class HumanName implements ValidationRule
{
    /**
     * Longer than any real name and far shorter than a pitch. The attack's payload ran to about
     * 230 characters; the longest names in the world are nowhere near this.
     */
    private const MAX = 80;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        // A line break in a name is either a paste accident or an attempt to inject a header
        // into the mail we are about to send. Neither should reach a queue.
        if (preg_match('/[\r\n\t]/', $value)) {
            $fail('The :attribute may not contain line breaks.');

            return;
        }

        if (mb_strlen(trim($value)) > self::MAX) {
            $fail('The :attribute may not be longer than '.self::MAX.' characters.');

            return;
        }

        // Anything that reads as a link. The scheme and `www.` catch the obvious; the third
        // pattern catches the form this attack actually used -- a bare `graph.org/xxxx` with no
        // scheme at all, which is what survives a naive "does it start with http" check.
        $linkish = [
            '~https?://~i',
            '~\bwww\.~i',
            '~\b[a-z0-9-]+\.[a-z]{2,}[/?]~i',
            '~[<>]~',          // markup, on its way into an HTML mail
            '~\[url~i',        // bbcode, still used by older spam kits
        ];

        foreach ($linkish as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail('The :attribute may not contain a web address.');

                return;
            }
        }
    }
}
