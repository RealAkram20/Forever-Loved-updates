@php
    $mode = $props['mode'] ?? 'iframe_url';
    $out = '';
    if ($mode === 'iframe_url') {
        $url = $props['iframe_url'] ?? '';
        if ($url !== '' && \App\Services\WidgetHtmlSanitizer::isAllowedIframeUrl($url)) {
            $srcEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $out = '<iframe src="'.$srcEsc.'" width="100%" height="400" title="Embedded content" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen class="max-w-full rounded-lg border border-gray-200 dark:border-gray-700"></iframe>';
        }
    } else {
        $out = \App\Services\WidgetHtmlSanitizer::embedHtml($props['html'] ?? '');
    }
@endphp
@if ($out !== '')
<section class="py-4 sm:py-6">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="flex justify-center">
            {!! $out !!}
        </div>
    </div>
</section>
@endif
