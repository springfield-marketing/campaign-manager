<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">
            {{ __('Emails Module') }}
        </h2>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="ui-card overflow-hidden">
                <div class="space-y-6 p-6">
                    <div>
                        <h3 class="ui-title">Foundation</h3>
                        <p class="mt-2 text-sm ui-muted">
                            This module keeps email concerns separated so future campaign engines, mail providers,
                            tracking, and compliance features have a clear home.
                        </p>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wide ui-muted">Planned capabilities</h4>
                        <ul class="mt-3 space-y-2 text-sm ui-strong">
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
