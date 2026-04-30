<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-[#0D0D0D]">Numbers</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="ivr-panel bg-white p-5">
                <form method="GET" class="grid gap-4">
                    @php
                        $includedSources = collect(request()->input('source_include', []))->map(fn ($source) => (string) $source)->all();
                        $excludedSources = collect(request()->input('source_exclude', []))->map(fn ($source) => (string) $source)->all();
                    @endphp

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label for="source_include" class="text-sm text-[#595859]">Include sources</label>
                            <select id="source_include" name="source_include[]" multiple size="5" class="mt-1 w-full rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                                @foreach ($availableSources as $source)
                                    <option value="{{ $source }}" @selected(in_array((string) $source, $includedSources, true))>{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="source_exclude" class="text-sm text-[#595859]">Exclude sources</label>
                            <select id="source_exclude" name="source_exclude[]" multiple size="5" class="mt-1 w-full rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                                @foreach ($availableSources as $source)
                                    <option value="{{ $source }}" @selected(in_array((string) $source, $excludedSources, true))>{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-6">
                    <select name="city" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                        <option value="">All cities</option>
                        @foreach (['Dubai', 'Abu Dhabi'] as $city)
                            <option value="{{ $city }}" @selected(request('city') == $city)>{{ $city }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        @foreach (['active', 'inactive', 'dead'] as $status)
                            <option value="{{ $status }}" @selected(request('status') == $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="uses_min" value="{{ request('uses_min') }}" placeholder="Min uses" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                    <input type="number" name="uses_max" value="{{ request('uses_max') }}" placeholder="Max uses" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                    <input type="number" name="export_limit" min="1" max="50000" value="{{ request('export_limit', 1000) }}" placeholder="Export rows" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                    <div class="flex gap-2">
                        <button type="submit" class="ivr-btn-accent px-3 py-2 text-sm">Filter</button>
                        <button type="submit" formaction="{{ route('modules.ivr.numbers.export') }}" class="ivr-btn-accent px-3 py-2 text-sm">Export</button>
                    </div>
                    </div>
                </form>
            </div>

            <div class="ivr-panel mt-6 overflow-hidden bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-[#D9D9D9] text-[#595859]">
                            <tr>
                                <th class="px-5 py-3 font-medium">Name</th>
                                <th class="px-5 py-3 font-medium">Phone</th>
                                <th class="px-5 py-3 font-medium">City</th>
                                <th class="px-5 py-3 font-medium">Source</th>
                                <th class="px-5 py-3 font-medium">Uses</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Cooldown</th>
                                <th class="px-5 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($numbers as $number)
                                <tr class="border-b border-[#D9D9D9]">
                                    <td class="px-5 py-3">{{ $number->client?->full_name ?: '-' }}</td>
                                    <td class="px-5 py-3">{{ $number->normalized_phone }}</td>
                                    <td class="px-5 py-3">{{ $number->client?->city ?: '-' }}</td>
                                    <td class="px-5 py-3">{{ $number->last_source_name ?: '-' }}</td>
                                    <td class="px-5 py-3">{{ $number->ivr_use_count }}</td>
                                    <td class="px-5 py-3">{{ ucfirst($number->usage_status) }}</td>
                                    <td class="px-5 py-3">{{ optional($number->cooldown_until)->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('modules.ivr.numbers.show', $number) }}" class="text-[#262526]">History</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-6 text-[#595859]">No UAE numbers available yet.</td>
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
