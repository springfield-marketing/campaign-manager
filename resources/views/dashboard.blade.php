<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Channel Modules') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 md:grid-cols-3">
                @foreach ($modules as $module)
                    <a href="{{ route($module->route) }}" class="block bg-white overflow-hidden shadow-sm sm:rounded-lg hover:shadow-md transition">
                        <div class="p-6 text-gray-900">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold">{{ $module->name }}</h3>
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium uppercase tracking-wide text-gray-600">
                                    Ready
                                </span>
                            </div>

                            <p class="mt-3 text-sm text-gray-600">
                                {{ $module->description }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-sm text-gray-600">
                    The initial modules are isolated so each channel can grow its own routes, views, services,
                    jobs, policies, and data model without forcing a large rewrite later.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
