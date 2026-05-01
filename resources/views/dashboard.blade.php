<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">
            {{ __('Channel Modules') }}
        </h2>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($modules as $module)
                    <a href="{{ route($module->route) }}" class="ui-card block overflow-hidden">
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="ui-title">{{ $module->name }}</h3>
                                <span class="ui-pill uppercase tracking-wide">
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

            <div class="ui-card mt-4 overflow-hidden">
                <div class="p-4 text-sm text-theme-secondary">
                    The initial modules are isolated so each channel can grow its own routes, views, services,
                    jobs, policies, and data model without forcing a large rewrite later.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
