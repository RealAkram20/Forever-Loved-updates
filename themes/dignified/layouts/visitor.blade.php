{{--
    The Dignified shell.

    hideFullscreenThemeToggle: this template is a committed light design — a gold-on-black
    hero over a cream page. A floating sun/moon button in the corner of a funeral home's
    front page is the kind of detail that reads as unserious, and the palette has no dark
    counterpart worth switching to.

    The two accent colours are published as custom properties rather than hardcoded through
    the markup, so a reseller who changes their brand colours on the Appearance page moves
    the whole template with them. The theme's own tokens supply the defaults (gold #F9BE1C,
    crimson #BB1520), which is exactly the layering ThemeSetting already does: theme sets it,
    reseller overrules it.
--}}
@extends('layouts.fullscreen-layout', ['hideFullscreenThemeToggle' => true])

@push('head')
@include('dg-theme-style')
@endpush

@section('content')
<div class="min-h-screen flex flex-col bg-[var(--dg-paper)] text-[var(--dg-ink)]">
    <x-home-header />

    <main class="flex-1">
        @yield('page')
    </main>

    <x-visitor-footer />
</div>
@endsection
