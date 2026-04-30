<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Emails Module') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Foundation</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            This module keeps email concerns separated so future campaign engines, mail providers,
                            tracking, and compliance features have a clear home.
                        </p>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Planned capabilities</h4>
                        <ul class="mt-3 space-y-2 text-sm text-gray-700">
                            @foreach ($capabilities as $capability)
                                <li>{{ $capability }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
