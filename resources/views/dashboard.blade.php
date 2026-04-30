<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl leading-8">
            {{ __('Channel Modules') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($modules as $module)
                    <a href="{{ route($module->route) }}" class="block ivr-panel overflow-hidden border">
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold">{{ $module->name }}</h3>
                                <span class="border px-2 py-1 text-xs font-medium uppercase tracking-wide">
                                    Ready
                                </span>
                            </div>

                            <p class="mt-4 text-sm text-theme-secondary">
                                {{ $module->description }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-4 ivr-panel overflow-hidden border">
                <div class="p-4 text-sm text-theme-secondary">
                    The initial modules are isolated so each channel can grow its own routes, views, services,
                    jobs, policies, and data model without forcing a large rewrite later.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
