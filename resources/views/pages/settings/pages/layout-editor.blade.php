@extends('layouts.app')

@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endpush

@section('content')
    @php
        $isCreateMode = $isCreateMode ?? false;
        $cmsPathPrefix = '';
        if ($isCreateMode) {
            $pageBuilderPayload = [
                'definitions' => $widgetDefinitions,
                'initial' => $initialDocument,
                'createMode' => true,
                'storeUrl' => route('settings.pages.store'),
                'previewUrl' => route('settings.pages.preview'),
                'slugEditable' => true,
                'originalSlug' => '',
                'cmsPathPrefix' => $cmsPathPrefix,
                'publicUrlBase' => rtrim(config('app.url'), '/') . $cmsPathPrefix . '/',
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
            $pageBuilderPayload = [
                'definitions' => $widgetDefinitions,
                'initial' => $initialDocument,
                'createMode' => false,
                'saveUrl' => route('settings.pages.layout.update', $page->slug),
                'previewUrl' => route('settings.pages.preview'),
                'seoUrl' => route('settings.pages.meta.update', $page->slug),
                'slugEditable' => ! $page->isSystemLayoutPage(),
                'originalSlug' => $page->slug,
                'cmsPathPrefix' => $cmsPathPrefix,
                'publicUrl' => $page->publicUrl(),
                'seo' => [
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'meta_title' => $page->meta_title ?? '',
                    'meta_description' => $page->meta_description ?? '',
                    'is_published' => (bool) $page->is_published,
                    'og_image_url' => $ogPublicUrl,
                ],
            ];
            $publicUrl = $page->publicUrl();
        }
    @endphp

    @include('pages.shared.page-builder-editor', [
        'pageBuilderPayload' => $pageBuilderPayload,
        'widgetDefinitions' => $widgetDefinitions,
        'layoutHeading' => $layoutHeading,
        'isCreateMode' => $isCreateMode,
        'page' => $page ?? null,
        'indexUrl' => route('settings.pages.index'),
        'publicUrl' => $publicUrl,
    ])
@endsection
