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
        <div class="flex min-h-screen flex-col bg-white">
            <header class="app-topbar border-b border-[#D9D9D9] bg-white">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('dashboard') }}" class="flex h-10 w-10 items-center justify-center rounded-[4px] border border-[#D9D9D9] text-[#262526]" aria-label="{{ config('app.name', 'Laravel') }}">
                            <x-application-logo class="h-6 w-6 fill-current" />
                        </a>

                        <div>
                            <p class="text-sm font-semibold text-[#0D0D0D]">{{ config('app.name', 'Campaign Tracker') }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-[#595859]">
                                @if (request()->routeIs('modules.ivr.*'))
                                    <span class="rounded-[4px] border border-[#8C8C8C] px-2 py-1 text-[#262526]">IVR workspace</span>
                                @else
                                    <span class="rounded-[4px] border border-[#8C8C8C] px-2 py-1 text-[#262526]">Campaign workspace</span>
                                @endif
                                <span>{{ now()->format('M d, Y') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-[4px] border border-[#262526] px-3 py-2 text-sm text-[#262526]">Sign out</button>
                        </form>
                    </div>
                </div>
            </header>

            @isset($header)
                <header class="app-header border-b border-[#D9D9D9] bg-white">
                    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div>
                            {{ $header }}
                        </div>

                        @if (request()->routeIs('modules.ivr.*'))
                            @include('ivr::partials.section-nav')
                        @endif
                    </div>
                </header>
            @endisset

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="app-footer border-t border-[#D9D9D9] bg-white">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 text-sm text-[#595859] sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <div class="flex flex-wrap items-center gap-4">
                        <a href="{{ route('dashboard') }}" class="text-[#262526]">Dashboard</a>
                        <a href="{{ route('modules.ivr.index') }}" class="text-[#262526]">IVR</a>
                        <a href="{{ route('modules.whatsapp.index') }}" class="text-[#262526]">WhatsApp</a>
                        <a href="{{ route('modules.emails.index') }}" class="text-[#262526]">Emails</a>
                    </div>

                    <p>IVR campaign management</p>
                </div>
            </footer>
        </div>
    </body>
</html>
