@extends('layouts.visitor')

{{--
    The front page, before a reseller has built one of their own.

    This used to be six hand-written section includes. Every one of them now exists as a page
    builder widget with a themed view, so keeping the blades meant two implementations of the
    same six sections — and the one a reseller could actually edit was the one nobody was
    looking at while designing.

    So the fallback renders the template's own `default_pages` document through the ordinary
    widget renderer. The page a reseller sees before touching anything is byte-for-byte the
    page they get in the builder, because it is the same document.

    PageController::home() serves a reseller's own layout ahead of this whenever they have one.
--}}

@php
    use App\PageBuilder\Widgets\MemorialShowcaseWidget;
    use App\PageBuilder\Widgets\PricingPlansWidget;

    $manifest = app(\App\Themes\ThemeRegistry::class)->manifest(
        app(\App\Themes\ActiveTheme::class)->template() ?? \App\Themes\ThemeRegistry::BASE
    );

    // Image paths stay as `{theme}/...` here; SectionRender::image() resolves them at render
    // so the same document works on a subdirectory install and a custom domain alike.
    $document = $manifest?->defaultPages['visitor-home'] ?? null;
    $widgets = $document['widgets'] ?? [];

    // The same context the builder gives a page, so a showcase or pricing widget dropped into
    // the default document has its data rather than rendering empty.
    $types = collect($widgets)->pluck('type')->all();
    $tenant = \App\Helpers\ThemeSetting::siteTenant();
    $context = $tenant
        ? \App\Support\ResellerPageContext::forWidgets($tenant, $types)
        : ['popularMemorials' => $popularMemorials ?? collect(), 'tagline' => $tagline ?? ''];
@endphp

@section('page')
    <x-page-layout.renderer :widgets="$widgets" :context="$context" />
@endsection
