<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Memorial')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1f2937;
            background: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #101828; color: #e5e7eb; }
        }
        a { color: inherit; }
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
