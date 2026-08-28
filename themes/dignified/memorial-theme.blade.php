{{--
    Dignified, on a memorial page.

    Pulled in by `pages/memorials/public.blade.php` through @includeIf, so it exists only for
    resellers running this template and costs every other site nothing.

    Scope is deliberately the hero and only the hero: the backdrop, the name, the dates and the
    rule under them. Everything below — the tabs, the tributes, the gallery, the comments, the
    timeline — is left exactly as the platform built it. Those are working parts and somebody's
    memories; a theme has no business restyling either, and a rule written here that reached
    them would be found by a grieving family rather than by us.

    The colours are read from BrandingHelper rather than hardcoded for the same reason the
    layout does it: a reseller who changes their brand colours moves this with them. The
    variables are redeclared here rather than borrowed, because this page extends
    layouts/fullscreen-layout and never passes through this template's own visitor layout.
--}}
{{-- The template's own tokens first, and the same file the site itself uses.

     This page renders <x-home-header /> — this template's header — from a layout that never
     defines them, so the nav bar was drawing white text on `bg-[var(--dg-ink)]`, an undefined
     variable, which resolves to nothing. On every memorial hosted by a reseller on this
     template, the navigation was invisible. --}}
@include('dg-theme-style')

@php
    $dgGold = \App\Helpers\BrandingHelper::accentColor();
    $dgRed = \App\Helpers\BrandingHelper::primaryColor();
@endphp
<style>
    /* Where to crop the backdrop this template ships.

       The band is a wide, short sliver, so only a fraction of the artwork can ever show. The
       platform's own scene is square and framed low and right; this one is a wide plate with
       flowers massed in the two lower corners and a dark middle, and it wants to be read
       across its lower half — which also leaves the centre dark, where the portrait sits. */
    :root { --t-memorial-backdrop-position: 50% 60%; }

    /* "In Loving Memory" — the template's eyebrow voice: gold, spaced, and in the body face
       rather than the serif, exactly as it is above every section of the main site. */
    .memorial-hero__eyebrow {
        font-family: 'Lato', system-ui, sans-serif;
        color: {{ $dgGold }};
    }

    .dark .memorial-hero__eyebrow { color: {{ $dgGold }}; }

    /* The name carries the template's heading face and its small caps. This is the single
       change that makes the page read as the same brand as the site it is hosted on. */
    .memorial-hero__name {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-variant-caps: small-caps;
        letter-spacing: 0.015em;
        font-weight: 500;
        color: #1a1a1a;
    }

    .dark .memorial-hero__name { color: #f7f7f7; }

    .memorial-hero__years {
        font-family: 'Cormorant Garamond', Georgia, serif;
        color: {{ $dgRed }};
    }

    .dark .memorial-hero__years { color: {{ $dgGold }}; }

    /* Line, heart, line. Gold, like every other rule on this template. */
    .memorial-hero__divider { color: {{ $dgGold }}; }
    .dark .memorial-hero__divider { color: {{ $dgGold }}; }
</style>
