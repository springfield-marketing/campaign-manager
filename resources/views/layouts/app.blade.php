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
    <body
        x-data="{
            darkMode: localStorage.getItem('theme') === 'dark',
            toggleTheme() {
                this.darkMode = ! this.darkMode;
                localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
            },
        }"
        :class="{ 'theme-dark': darkMode }"
        class="font-sans antialiased"
    >
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
                        <button
                            type="button"
                            class="rounded-[4px] border border-[#8C8C8C] p-2 text-[#262526]"
                            @click="toggleTheme()"
                            :aria-label="darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
                            :title="darkMode ? 'Light mode' : 'Dark mode'"
                        >
                            <svg x-show="!darkMode" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <circle cx="12" cy="12" r="4" />
                                <path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41" />
                            </svg>
                            <svg x-show="darkMode" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
                            </svg>
                        </button>

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
