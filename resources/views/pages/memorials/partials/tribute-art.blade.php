{{--
    Artwork for a one-tap tribute card.

    Prefers a real PNG at public/images/tributes/{file} when one has been added, and
    otherwise draws the motif inline. The inline version is the default rather than a
    placeholder: the cards have to look finished on a fresh checkout, before anyone has
    dropped artwork in, and a missing file should never leave an empty square.

    @param string $type   story | flower | candle | prayer
    @param string $image  filename to look for, e.g. rose.png
--}}
@php
    // A template may bring its own. Same shape as the memorial backdrop above it:
    // `public/images/themes/{template}/tributes/{file}`, and every template that ships none —
    // including the base one — keeps the platform's, so nothing changes for anyone who has not
    // opted in. Per-file rather than all-or-nothing: a template can replace the rose and leave
    // the candle and the hands alone.
    $artTemplate = app(\App\Themes\ActiveTheme::class)->template();
    $artRel = 'images/themes/'.$artTemplate.'/tributes/'.$image;

    if (! is_file(public_path($artRel))) {
        $artRel = 'images/tributes/'.$image;
    }

    $artPath = public_path($artRel);
    $hasArtwork = is_file($artPath);
    // Stamped with the file's own mtime so replacing the artwork replaces the URL.
    // Without it these are cached under a name that never changes, and swapping the file
    // leaves everyone who has already seen the page looking at the old picture — which is
    // the one thing the "just drop a file in" promise in this folder's README cannot
    // afford. The stat is already being done a line above for is_file().
    $artVersion = $hasArtwork ? filemtime($artPath) : null;
@endphp

@if ($hasArtwork)
    <img src="{{ asset($artRel).'?v='.$artVersion }}" alt="" aria-hidden="true"
        class="h-full w-full object-contain" loading="lazy" />
@elseif ($type === 'flower')
    <svg viewBox="0 0 64 64" fill="none" class="h-full w-full" aria-hidden="true">
        <g class="tribute-icon-sway" style="transform-origin: 32px 58px">
            <path d="M32 60V34" stroke="#4ade80" stroke-width="3" stroke-linecap="round"/>
            <path d="M32 46c-6 0-11-3-13-8 6-2 11 0 13 4z" fill="#22c55e"/>
            <path d="M32 40c5 0 9-2 11-7-5-2-9 0-11 4z" fill="#4ade80"/>
            <g transform="translate(32,24)">
                <ellipse rx="9" ry="14" fill="#aa5796" transform="rotate(0)"/>
                <ellipse rx="9" ry="14" fill="#c96a9d" transform="rotate(60)"/>
                <ellipse rx="9" ry="14" fill="#aa5796" transform="rotate(120)"/>
                <ellipse rx="8" ry="11" fill="#dd6f8f" transform="rotate(30)"/>
                <ellipse rx="8" ry="11" fill="#c96a9d" transform="rotate(90)"/>
                <ellipse rx="8" ry="11" fill="#dd6f8f" transform="rotate(150)"/>
                <circle r="5.5" fill="#f0857a"/>
                <circle r="2.5" fill="#f8a48f"/>
            </g>
        </g>
        <path d="M50 40c3-1 6 1 6 4s-4 4-6 2-2-5 0-6z" fill="#c96a9d" opacity="0.85"/>
        <path d="M46 54c2.5-1 5 .8 5 3.2s-3.2 3.3-4.9 1.6-1.8-4-.1-4.8z" fill="#f0a08c" opacity="0.7"/>
    </svg>
@elseif ($type === 'candle')
    <svg viewBox="0 0 64 64" fill="none" class="h-full w-full" aria-hidden="true">
        <ellipse cx="32" cy="30" rx="17" ry="16" fill="#fbbf24" opacity="0.18" class="tribute-glow-pulse"/>
        <ellipse cx="32" cy="56" rx="20" ry="5" fill="#b45309"/>
        <ellipse cx="32" cy="54" rx="20" ry="5" fill="#d97706"/>
        <ellipse cx="32" cy="53" rx="14" ry="3.2" fill="#92400e"/>
        <rect x="21" y="26" width="22" height="27" rx="3" fill="#fef3c7"/>
        <rect x="21" y="26" width="7" height="27" fill="#fffbeb" opacity="0.7"/>
        <path d="M21 29c0-2 1.5-3.5 3.5-3.5h15c2 0 3.5 1.5 3.5 3.5v3c-2 1.5-3 .5-4.5 2s-3-.5-4.5 1-3-1-4.5.5-3 .5-4.5-1-2.5.5-4-.5z" fill="#fde68a"/>
        <path d="M24 32v6c0 1.5 2 1.5 2 0v-6z" fill="#fde68a"/>
        <path d="M38 32v8c0 1.5 2 1.5 2 0v-8z" fill="#fde68a"/>
        <rect x="31" y="20" width="2" height="7" rx="1" fill="#78350f"/>
        <g class="tribute-flame-flicker" style="transform-origin: 32px 18px">
            <path d="M32 4c4 6 7 9 7 13a7 7 0 01-14 0c0-4 3-7 7-13z" fill="#f59e0b"/>
            <path d="M32 9c2.4 4 4 6 4 8.6a4 4 0 01-8 0c0-2.6 1.6-4.6 4-8.6z" fill="#fcd34d"/>
            <path d="M32 13c1.2 2.2 2 3.3 2 4.7a2 2 0 01-4 0c0-1.4.8-2.5 2-4.7z" fill="#fffbeb"/>
        </g>
    </svg>
@elseif ($type === 'story')
    {{-- The unmarked case, and the common one: somebody just wrote something. A written
         page rather than an object, so it reads as the absence of a gesture rather than as
         a fourth gesture competing with the other three. --}}
    <svg viewBox="0 0 64 64" fill="none" class="h-full w-full" aria-hidden="true">
        <path d="M13 12a4 4 0 0 1 4-4h21l13 13v31a4 4 0 0 1-4 4H17a4 4 0 0 1-4-4z" fill="#eef2ff" stroke="#a5b4fc" stroke-width="2.5" stroke-linejoin="round"/>
        <path d="M38 8v9a4 4 0 0 0 4 4h9" fill="#c7d2fe" stroke="#a5b4fc" stroke-width="2.5" stroke-linejoin="round"/>
        <g stroke="#818cf8" stroke-width="3" stroke-linecap="round" opacity="0.7">
            <path d="M21 32h22M21 40h22M21 48h14"/>
        </g>
    </svg>
@else
    <svg viewBox="0 0 48 48" fill="none" class="h-full w-full" aria-hidden="true">
        <g stroke="#fbbf24" stroke-width="1.6" stroke-linecap="round" opacity="0.5">
            <path d="M24 2.5v3M12.9 6.8l1.8 2.5M35.1 6.8l-1.8 2.5M6.4 17.2l3 .8M41.6 17.2l-3 .8"/>
        </g>
        <circle cx="24" cy="17" r="12" fill="#fbbf24" opacity="0.25" class="tribute-glow-pulse"/>
        {{-- Far hand and its cuff in the shaded tones; the near pair is the mirror of them --}}
        <g transform="translate(48,0) scale(-1,1)">
            <path d="M22.9 3.5c-2.2 2-3.9 4.6-5.1 7.7-1.7 4.3-2.6 8.8-2.6 13.5v5.6c0 2.9 1.8 5.5 4.5 6.5l3.2 1.2z" fill="#c07f4a"/>
            <path d="M22.9 33v6.5l-4.4 2.7a4.8 4.8 0 0 1-6.6-1.6 4.8 4.8 0 0 1 1.6-6.6l5.4-3.3z" fill="#6b7d94"/>
            <path d="M15.6 35.4a4.8 4.8 0 0 0-1.6 6.6" stroke="#4e5c70" stroke-width="1.3" stroke-linecap="round"/>
        </g>
        <path d="M22.9 3.5c-2.2 2-3.9 4.6-5.1 7.7-1.7 4.3-2.6 8.8-2.6 13.5v5.6c0 2.9 1.8 5.5 4.5 6.5l3.2 1.2z" fill="#e8ab77"/>
        <path d="M20.2 8.2c-1.9 2.6-3.3 5.6-4.1 8.9M22 13.6c-1.3 2.3-2.2 4.7-2.7 7.3" stroke="#c07f4a" stroke-width="0.9" stroke-linecap="round" opacity="0.55"/>
        <path d="M22.9 33v6.5l-4.4 2.7a4.8 4.8 0 0 1-6.6-1.6 4.8 4.8 0 0 1 1.6-6.6l5.4-3.3z" fill="#94a3b8"/>
        <path d="M15.6 35.4a4.8 4.8 0 0 0-1.6 6.6" stroke="#6b7d94" stroke-width="1.3" stroke-linecap="round"/>
    </svg>
@endif
