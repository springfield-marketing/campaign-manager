<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="app-shell items-center justify-center px-4 py-8">
            <div>
                <a href="/">
                    <x-application-logo class="h-16 w-16 fill-current text-theme-muted" />
                </a>
            </div>

            <div class="ui-card mt-6 w-full overflow-hidden px-6 py-5 sm:max-w-md">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
