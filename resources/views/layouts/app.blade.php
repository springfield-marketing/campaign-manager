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
        <div class="app-shell">
            <header class="app-bar">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('dashboard') }}" class="ui-button-subtle h-10 w-10 p-0" aria-label="{{ config('app.name', 'Laravel') }}">
                            <x-application-logo class="h-6 w-6 fill-current" />
                        </a>

                        <div>
                            <p class="text-sm font-semibold text-theme-primary">{{ config('app.name', 'Campaign Tracker') }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-theme-muted">
                                @if (request()->routeIs('modules.ivr.*'))
                                    <span class="ui-pill">IVR workspace</span>
                                @elseif (request()->routeIs('modules.whatsapp.*'))
                                    <span class="ui-pill">WhatsApp workspace</span>
                                @else
                                    <span class="ui-pill">Campaign workspace</span>
                                @endif
                                <span>{{ now()->format('M d, Y') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="ui-button-subtle">Sign out</button>
                        </form>
                    </div>
                </div>
            </header>

            @isset($header)
                <header class="app-bar">
                    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div>
                            {{ $header }}
                        </div>

                        @if (request()->routeIs('modules.ivr.*'))
                            @include('ivr::partials.section-nav')
                        @elseif (request()->routeIs('modules.whatsapp.*'))
                            @include('whatsapp::partials.section-nav')
                        @endif
                    </div>
                </header>
            @endisset

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="app-footer">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <div class="flex flex-wrap items-center gap-4">
                        <a href="{{ route('dashboard') }}" class="ui-link">Dashboard</a>
                        <a href="{{ route('modules.ivr.index') }}" class="ui-link">IVR</a>
                        <a href="{{ route('modules.whatsapp.index') }}" class="ui-link">WhatsApp</a>
                        <a href="{{ route('modules.emails.index') }}" class="ui-link">Emails</a>
                    </div>

                    <p>
                        @if (request()->routeIs('modules.whatsapp.*'))
                            WhatsApp campaign management
                        @else
                            IVR campaign management
                        @endif
                    </p>
                </div>
            </footer>
        </div>
    </body>
</html>
