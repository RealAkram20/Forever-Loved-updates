{{--
    Chrome-less layout for the embeddable memorial widget.

    Carries the tenant's branding: WidgetController binds the memorial's reseller into the
    container, so BrandingHelper and AppearanceHelper below resolve that reseller's palette,
    fonts and favicon. This file previously hardcoded its fonts and colours, which meant
    feature_embedding — a billable tier flag sold as "drop a memorial onto your own website" —
    produced a generic grey iframe with none of the reseller's identity in it.

    Deliberately variables and @font-face only, no compiled stylesheet: this renders inside
    someone else's page, so it stays small and carries nothing that could affect the host page.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Memorial')</title>

    <link rel="icon" href="{{ \App\Helpers\BrandingHelper::faviconUrl() }}" />

    {!! \App\Helpers\AppearanceHelper::fontLinks() !!}
    <style>{{ \App\Helpers\BrandingHelper::brandColorCss() }}</style>
    <style>{!! \App\Helpers\AppearanceHelper::css() !!}</style>

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            /* AppearanceHelper::css() overrides --font-outfit when a body font is chosen, so a
               reseller's typography reaches the widget; the rest of the stack is the fallback. */
            font-family: var(--font-outfit, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
            color: #1f2937;
            background: var(--color-bg-page, #ffffff);
        }
        @media (prefers-color-scheme: dark) {
            body { background: #101828; color: #e5e7eb; }
        }
        a { color: inherit; }

        /* The widget's one interactive element, themed from the tenant's own button colours
           rather than the hardcoded #465fff this used to carry. */
        .embed-cta {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            background: var(--color-btn-primary, #465fff);
            color: var(--color-btn-primary-text, #ffffff);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .embed-logo { max-height: 28px; max-width: 160px; margin: 0 auto 18px; display: block; }
    </style>
</head>
<body>
    @yield('content')
    <script>
        function postHeight() {
            window.parent.postMessage({ type: 'foreverloved:resize', height: document.documentElement.scrollHeight }, '*');
        }
        window.addEventListener('load', postHeight);
        new ResizeObserver(postHeight).observe(document.body);
    </script>
</body>
</html>
