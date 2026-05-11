<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Numbers</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <section class="grid gap-2 sm:grid-cols-2 lg:grid-cols-7">
                <article class="ui-card px-3 py-2">
                    <p class="text-[11px] font-medium uppercase ui-muted">Campaign ready</p>
                    <p class="text-lg font-semibold ui-strong">{{ number_format($stats['campaign_ready']) }}</p>
                </article>

                <article class="ui-card px-3 py-2">
                    <p class="text-[11px] font-medium uppercase ui-muted">Total numbers</p>
                    <p class="text-lg font-semibold ui-strong">{{ number_format($stats['total']) }}</p>
                </article>

                <article class="ui-card px-3 py-2">
                    <p class="text-[11px] font-medium uppercase ui-muted">Active status</p>
                    <p class="text-lg font-semibold ui-strong">{{ number_format($stats['active']) }}</p>
                </article>

                <article class="ui-card px-3 py-2">
                    <p class="text-[11px] font-medium uppercase ui-muted">Unsubscribers</p>
                    <p class="text-lg font-semibold ui-strong">{{ number_format($stats['unsubscribers']) }}</p>
                </article>

                <article class="ui-card px-3 py-2">
                    <p class="text-[11px] font-medium uppercase ui-muted">Cooldown</p>
                    <p class="text-lg font-semibold ui-strong">{{ number_format($stats['cooldown']) }}</p>
                </article>

                <article class="ui-card px-3 py-2">
                    <p class="text-[11px] font-medium uppercase ui-muted">Inactive</p>
                    <p class="text-lg font-semibold ui-strong">{{ number_format($stats['inactive']) }}</p>
                </article>

                <article class="ui-card px-3 py-2">
                    <p class="text-[11px] font-medium uppercase ui-muted">Dead</p>
                    <p class="text-lg font-semibold ui-strong">{{ number_format($stats['dead']) }}</p>
                </article>
            </section>

            <div class="ui-card ui-card-pad mt-6">
                <form method="GET" class="grid gap-4">
                    @php
                        $includedSources = collect(request()->input('source_include', []))->map(fn ($source) => (string) $source)->all();
                        $excludedSources = collect(request()->input('source_exclude', []))->map(fn ($source) => (string) $source)->all();
                    @endphp

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label for="source_include" class="ui-label">Include sources</label>
                            <select id="source_include" name="source_include[]" multiple size="5" class="ui-control mt-1 w-full">
                                @foreach ($availableSources as $source)
                                    <option value="{{ $source }}" @selected(in_array((string) $source, $includedSources, true))>{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="source_exclude" class="ui-label">Exclude sources</label>
                            <select id="source_exclude" name="source_exclude[]" multiple size="5" class="ui-control mt-1 w-full">
                                @foreach ($availableSources as $source)
                                    <option value="{{ $source }}" @selected(in_array((string) $source, $excludedSources, true))>{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-7">
                    <input type="search" name="phone" value="{{ request('phone') }}" placeholder="Search number" class="ui-control">
                    <select name="city" class="ui-control">
                        <option value="">All cities</option>
                        @foreach (['Dubai', 'Abu Dhabi'] as $city)
                            <option value="{{ $city }}" @selected(request('city') == $city)>{{ $city }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="ui-control">
                        <option value="">All statuses</option>
                        @foreach (['active', 'inactive', 'dead'] as $status)
                            <option value="{{ $status }}" @selected(request('status') == $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="uses_min" value="{{ request('uses_min') }}" placeholder="Min uses" class="ui-control">
                    <input type="number" name="uses_max" value="{{ request('uses_max') }}" placeholder="Max uses" class="ui-control">
                    <input type="number" name="export_limit" min="1" max="50000" value="{{ request('export_limit', 1000) }}" placeholder="Export rows" class="ui-control">
                    <div class="flex gap-2">
                        <button type="submit" class="ui-button">Filter</button>
                        <button type="submit" formaction="{{ route('modules.ivr.numbers.export') }}" class="ui-button">Export</button>
                    </div>
                    </div>
                </form>
            </div>

            <div class="ui-card mt-6 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Source</th>
                                <th>Uses</th>
                                <th>Status</th>
                                <th>Cooldown</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($numbers as $number)
                                <tr>
                                    <td>{{ $number->client?->full_name ?: '-' }}</td>
                                    <td>{{ $number->normalized_phone }}</td>
                                    <td>{{ $number->client?->city ?: '-' }}</td>
                                    <td>{{ $number->last_source_name ?: '-' }}</td>
                                    <td>{{ $number->ivr_use_count }}</td>
                                    <td>{{ ucfirst($number->ivrProfile?->usage_status ?? 'active') }}</td>
                                    <td>{{ optional($number->ivrProfile?->cooldown_until)->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td>
                                        <a href="{{ route('modules.ivr.numbers.show', $number) }}" class="ui-link">History</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="ui-empty">No UAE numbers available yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4">
                    {{ $numbers->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
