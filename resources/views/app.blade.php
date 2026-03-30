<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? 'system' }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    {{-- Inline style to set the HTML background color based on our theme in app.css --}}
    <style>
        html {
            background-color: oklch(1 0 0);
        }

        html.dark {
            background-color: oklch(0.145 0 0);
        }
    </style>

    @if (config('broadcasting.default') === 'reverb' && config('broadcasting.connections.reverb.key'))
        <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
        @if (config('broadcasting.connections.reverb.echo.host'))
            <meta name="reverb-host" content="{{ config('broadcasting.connections.reverb.echo.host') }}">
        @endif
        <meta name="reverb-port" content="{{ config('broadcasting.connections.reverb.echo.port') }}">
        <meta name="reverb-scheme" content="{{ config('broadcasting.connections.reverb.echo.scheme') }}">
    @endif

    <title data-inertia>{{ config('app.name') }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

    @if (config('cashier.client_side_token'))
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
        @if (config('cashier.sandbox'))
            <script>Paddle.Environment.set('sandbox');</script>
        @endif
        <script>
            Paddle.Initialize({ token: @json(config('cashier.client_side_token')) });
        </script>
    @endif

    @viteReactRefresh
    @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
