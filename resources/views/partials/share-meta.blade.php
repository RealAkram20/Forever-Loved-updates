{{-- Open Graph + Twitter Card (WhatsApp, Facebook, LinkedIn, etc.) --}}
@if (!empty($shareMeta['title']) && !empty($shareMeta['description']) && !empty($shareMeta['url']))
<link rel="canonical" href="{{ $shareMeta['url'] }}">
<meta name="description" content="{{ $shareMeta['description'] }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $shareMeta['site_name'] ?? config('app.name') }}">
<meta property="og:title" content="{{ $shareMeta['title'] }}">
<meta property="og:description" content="{{ $shareMeta['description'] }}">
<meta property="og:url" content="{{ $shareMeta['url'] }}">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
@if($shareMeta['image'] ?? null)
<meta property="og:image" content="{{ $shareMeta['image'] }}">
@if($shareMeta['image_alt'] ?? null)
<meta property="og:image:alt" content="{{ $shareMeta['image_alt'] }}">
@endif
@endif
@if($shareMeta['image'] ?? null)
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ $shareMeta['image'] }}">
@if($shareMeta['image_alt'] ?? null)
<meta name="twitter:image:alt" content="{{ $shareMeta['image_alt'] }}">
@endif
@else
<meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="{{ $shareMeta['title'] }}">
<meta name="twitter:description" content="{{ $shareMeta['description'] }}">
@endif
