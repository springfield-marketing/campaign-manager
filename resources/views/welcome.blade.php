<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Campaign Tracker') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <main class="app-shell items-center justify-center px-4 py-10">
            <section class="ui-card w-full max-w-2xl overflow-hidden">
                <div class="p-6 sm:p-8">
                    <div class="mb-6 flex items-center gap-3">
                        <span class="ui-button-subtle h-10 w-10 p-0">
                            <x-application-logo class="h-6 w-6 fill-current" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-theme-primary">{{ config('app.name', 'Campaign Tracker') }}</p>
                            <p class="text-xs text-theme-muted">Campaign operations workspace</p>
                        </div>
                    </div>

                    <h1 class="text-3xl font-semibold text-theme-primary">Track channel campaigns without the clutter.</h1>
                    <p class="mt-3 text-sm leading-6 text-theme-secondary">
                        Import IVR files, review campaign outcomes, manage number eligibility, and keep future WhatsApp and email workflows neatly separated.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="ui-button">Open dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="ui-button">Log in</a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="ui-button-subtle">Create account</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
