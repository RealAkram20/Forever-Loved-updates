@extends('layouts.app')

@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endpush

@section('content')
    @php
        $isCreateMode = $isCreateMode ?? false;
        // Slugs resolve against the reseller's own public host (their subdomain, verified
        // custom domain, or the /r/{slug} dev fallback) — never the platform's app.url.
        $publicBase = rtrim($reseller->publicBaseUrl(), '/') . '/';
        if ($isCreateMode) {
            $pageBuilderPayload = [
                'definitions' => $widgetDefinitions,
                'initial' => $initialDocument,
                'createMode' => true,
                'storeUrl' => route('reseller.pages.store'),
                'previewUrl' => route('reseller.pages.preview'),
                'slugEditable' => true,
                'originalSlug' => '',
                'cmsPathPrefix' => '',
                'publicUrlBase' => $publicBase,
                'seo' => [
                    'slug' => old('slug', ''),
                    'title' => old('title', ''),
                    'meta_title' => old('meta_title', ''),
                    'meta_description' => old('meta_description', ''),
                    'is_published' => (bool) old('is_published', true),
                    'og_image_url' => null,
                ],
            ];
            $publicUrl = null;
        } else {
            $ogPublicUrl = null;
            if (is_string($page->og_image) && $page->og_image !== '') {
                $ogPublicUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($page->og_image);
            }
            // The homepage is served at the site root, with a fixed slug; a custom page is
            // served at /its-slug and can be re-slugged.
            $isHome = $page->isSystemLayoutPage();
            $publicUrl = $isHome
                ? rtrim($reseller->publicBaseUrl(), '/')
                : $reseller->publicUrlForSlug($page->slug);
            $pageBuilderPayload = [
                'definitions' => $widgetDefinitions,
                'initial' => $initialDocument,
                'createMode' => false,
                'saveUrl' => route('reseller.pages.layout.update', $page->slug),
                'previewUrl' => route('reseller.pages.preview'),
                'seoUrl' => route('reseller.pages.meta.update', $page->slug),
                'slugEditable' => ! $isHome,
                'originalSlug' => $page->slug,
                'cmsPathPrefix' => '',
                'publicUrlBase' => $publicBase,
                'publicUrl' => $publicUrl,
                'seo' => [
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'meta_title' => $page->meta_title ?? '',
                    'meta_description' => $page->meta_description ?? '',
                    'is_published' => (bool) $page->is_published,
                    'og_image_url' => $ogPublicUrl,
                ],
            ];
        }
    @endphp

    @include('pages.shared.page-builder-editor', [
        'pageBuilderPayload' => $pageBuilderPayload,
        'widgetDefinitions' => $widgetDefinitions,
        'layoutHeading' => $layoutHeading,
        'isCreateMode' => $isCreateMode,
        'page' => $page ?? null,
        'indexUrl' => route('reseller.pages.index'),
        'publicUrl' => $publicUrl,
    ])
@endsection
