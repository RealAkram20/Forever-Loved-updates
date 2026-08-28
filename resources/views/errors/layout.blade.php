{{--
    Standalone error layout. Must render when the database, session, or Vite
    manifest are unavailable, so: no @vite, no csrf_token(), and every
    branding lookup is rescue()'d (report: false — reporting from an error
    page could recurse) with hardcoded fallbacks.
--}}
@php
    // Tenant-aware so a 404 on a reseller's own domain does not advertise the platform.
    $appName = rescue(fn () => \App\Helpers\SiteShareMetaHelper::appDisplayName(), config('app.name', 'Forever-Loved'), false);
    $brandColor = \App\Helpers\BrandingHelper::sanitizeHex(
        rescue(fn () => \App\Helpers\BrandingHelper::primaryColor(), '#465fff', false),
        '#465fff'
    );
    $logoUrl = rescue(fn () => \App\Helpers\BrandingHelper::logoUrl(), asset('images/logo/logo.svg'), false);
    $logoDarkUrl = rescue(fn () => \App\Helpers\BrandingHelper::logoDarkUrl(), asset('images/logo/logo-dark.svg'), false);
    $faviconUrl = rescue(fn () => \App\Helpers\BrandingHelper::faviconUrl(), asset('favicon.ico'), false);
    $defaultTheme = rescue(fn () => \App\Helpers\BrandingHelper::defaultTheme(), 'light', false);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ $appName }}</title>
    <link rel="icon" href="{{ $faviconUrl }}" type="image/x-icon" />
    <script>
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('theme'); } catch (e) {}
            if ((theme || @json($defaultTheme)) === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <style>
        :root { --brand: {{ $brandColor }}; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f9fafb; color: #1d2939;
            min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }
        .dark body { background: #101828; color: #f2f4f7; }
        .logo { margin-bottom: 2rem; }
        .logo img { height: 3rem; width: auto; }
        .logo .logo-dark, .dark .logo .logo-light { display: none; }
        .dark .logo .logo-dark { display: block; }
        .card { text-align: center; max-width: 28rem; }
        .code {
            font-size: 5rem; font-weight: 700; line-height: 1;
            color: var(--brand); letter-spacing: -0.02em;
        }
        h1 { font-size: 1.375rem; font-weight: 600; margin: 1rem 0 0.625rem; }
        p { font-size: 0.9375rem; line-height: 1.6; color: #475467; }
        .dark p { color: #98a2b3; }
        .actions { margin-top: 2rem; display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: 0.625rem 1.5rem; border-radius: 0.5rem;
            font-size: 0.875rem; font-weight: 500; text-decoration: none;
            cursor: pointer; border: none; font-family: inherit;
        }
        .btn-primary { background: var(--brand); color: #ffffff; }
        .btn-primary:hover { filter: brightness(0.92); }
        .btn-secondary { background: transparent; color: #475467; border: 1px solid #d0d5dd; }
        .btn-secondary:hover { background: rgba(0, 0, 0, 0.04); }
        .dark .btn-secondary { color: #98a2b3; border-color: #344054; }
        .dark .btn-secondary:hover { background: rgba(255, 255, 255, 0.06); }
        .footer { margin-top: 3rem; font-size: 0.8125rem; color: #98a2b3; }
    </style>
</head>
<body>
    <div class="logo">
        <img class="logo-light" src="{{ $logoUrl }}" alt="{{ $appName }}">
        <img class="logo-dark" src="{{ $logoDarkUrl }}" alt="{{ $appName }}">
    </div>
    <div class="card">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @yield('actions')
            <a href="{{ \App\Support\SiteUrl::to('') }}" class="btn btn-secondary">Back to home</a>
        </div>
    </div>
    <div class="footer">{{ $appName }}</div>
</body>
</html>
