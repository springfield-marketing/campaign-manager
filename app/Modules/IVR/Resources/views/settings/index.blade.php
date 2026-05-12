<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">IVR Settings</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mt-6">
                    {{ session('status') }}
                </div>
            @endif

            <section class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title">Monthly quota & pricing</h3>
                <p class="mt-2 text-sm ui-muted">
                    These settings are used to calculate costs on the reports page.
                </p>

                <form method="POST" action="{{ route('modules.ivr.settings.update') }}" class="mt-6 grid gap-6 md:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="ui-label" for="monthly_minutes_quota">Monthly minutes quota</label>
                        <input
                            id="monthly_minutes_quota"
                            name="monthly_minutes_quota"
                            type="number"
                            min="1"
                            value="{{ old('monthly_minutes_quota', $settings->monthly_minutes_quota) }}"
                            class="ui-control mt-1 block w-full"
                        >
                        <x-input-error :messages="$errors->get('monthly_minutes_quota')" class="mt-2" />
                    </div>

                    <div>
                        <label class="ui-label" for="price_per_minute_under">Price per minute (under quota) — AED</label>
                        <input
                            id="price_per_minute_under"
                            name="price_per_minute_under"
                            type="number"
                            step="0.0001"
                            min="0"
                            value="{{ old('price_per_minute_under', number_format((float) $settings->price_per_minute_under, 4, '.', '')) }}"
                            class="ui-control mt-1 block w-full"
                        >
                        <x-input-error :messages="$errors->get('price_per_minute_under')" class="mt-2" />
                    </div>

                    <div>
                        <label class="ui-label" for="price_per_minute_over">Price per minute (over quota) — AED</label>
                        <input
                            id="price_per_minute_over"
                            name="price_per_minute_over"
                            type="number"
                            step="0.0001"
                            min="0"
                            value="{{ old('price_per_minute_over', number_format((float) $settings->price_per_minute_over, 4, '.', '')) }}"
                            class="ui-control mt-1 block w-full"
                        >
                        <x-input-error :messages="$errors->get('price_per_minute_over')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" class="ui-button">Save settings</button>
                    </div>
                </form>
            </section>

            <section class="ui-card ui-card-pad mt-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="ui-title">Central database export</h3>
                        <p class="mt-2 text-sm ui-muted">
                            Create a full Excel workbook of the business database for safekeeping or migration.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('modules.ivr.settings.database-export.store') }}">
                        @csrf
                        <button type="submit" class="ui-button">Start Excel export</button>
                    </form>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Size</th>
                                <th>Requested by</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($databaseExports as $export)
                                <tr>
                                    <td>{{ $export->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="capitalize">{{ str_replace('_', ' ', $export->status) }}</td>
                                    <td>
                                        {{ number_format($export->processed_rows) }} / {{ number_format($export->total_rows) }}
                                        <span class="ui-muted">({{ $export->progressPercent() }}%)</span>
                                        @if ($export->status === 'failed' && $export->error_message)
                                            <div class="mt-1 text-xs text-red-700">{{ $export->error_message }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $export->file_size ? number_format($export->file_size / 1024 / 1024, 2).' MB' : '-' }}
                                    </td>
                                    <td>{{ $export->requester?->name ?: '-' }}</td>
                                    <td class="text-right">
                                        @if ($export->status === 'completed')
                                            <a href="{{ route('modules.ivr.settings.database-export.download', $export) }}" class="ui-link">
                                                Download
                                            </a>
                                        @elseif (in_array($export->status, ['pending', 'processing'], true))
                                            <span class="ui-muted">Refresh to update</span>
                                        @else
                                            <span class="ui-muted">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="ui-empty">No database exports yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
