<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-[#0D0D0D]">Campaign Results</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-[4px] border border-[#8C8C8C] bg-white px-4 py-3 text-sm text-[#262526]">
                    {{ session('status') }}
                </div>
            @endif

            <div
                class="grid gap-6"
                x-data="importProgress({
                    endpoint: '{{ route('modules.ivr.results.status') }}',
                    imports: @js($imports->map(fn ($import) => [
                        'id' => $import->id,
                        'status' => $import->status,
                        'status_label' => str_replace('_', ' ', $import->status),
                        'total_rows' => $import->total_rows,
                        'processed_rows' => $import->processed_rows,
                        'successful_rows' => $import->successful_rows,
                        'failed_rows' => $import->failed_rows,
                        'duplicate_rows' => $import->duplicate_rows,
                        'progress' => $import->total_rows > 0 ? min(100, round(($import->processed_rows / $import->total_rows) * 100)) : 0,
                        'is_active' => in_array($import->status, ['pending', 'processing'], true),
                    ])->values())
                })"
                x-init="start()"
            >
                <section class="grid gap-6">
                    <article class="ivr-panel bg-white p-5">
                        <h3 class="text-lg font-semibold text-[#0D0D0D]">Import campaign report</h3>
                        <form method="POST" action="{{ route('modules.ivr.results.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="file" :value="__('Campaign CSV')" />
                                <input id="file" name="file" type="file" class="mt-1 block w-full rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                                <x-input-error :messages="$errors->get('file')" class="mt-2" />
                            </div>
                            <x-primary-button>Queue Results Import</x-primary-button>
                        </form>
                    </article>

                    <article class="ivr-panel overflow-hidden bg-white">
                        <div class="border-b border-[#D9D9D9] px-5 py-4">
                            <h3 class="text-lg font-semibold text-[#0D0D0D]">Result import history</h3>
                        </div>
                        <div class="max-h-[560px] divide-y divide-[#D9D9D9] overflow-y-auto">
                            @forelse ($imports as $import)
                                @php
                                    $campaignReference = data_get($import->summary, 'order_number') ?: pathinfo($import->original_file_name, PATHINFO_FILENAME);
                                    $campaign = $importCampaigns->get($campaignReference);
                                @endphp
                                <div class="px-5 py-4 text-sm" x-data="{ item: get({{ $import->id }}) }">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="break-all font-medium text-[#0D0D0D]">{{ $import->original_file_name }}</p>
                                            <p class="capitalize text-[#595859]" x-text="item.status_label"></p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                            @if ($campaign)
                                                <a href="{{ route('modules.ivr.results.show', $campaign) }}" class="rounded-[4px] border border-[#D9D9D9] px-2 py-1 text-xs text-[#262526]">Campaign</a>
                                            @endif

                                            <a href="{{ route('modules.ivr.imports.show', $import) }}" class="rounded-[4px] border border-[#D9D9D9] px-2 py-1 text-xs text-[#595859]">Import log</a>

                                            @if (! in_array($import->status, ['pending', 'processing', 'reverted'], true) && $import->reverted_at === null)
                                                <form method="POST" action="{{ route('modules.ivr.results.destroy', $import) }}" onsubmit="return confirm('Revert this campaign import? This will remove its campaign results and recalculate affected numbers.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-[4px] border border-[#D9D9D9] px-2 py-1 text-xs text-[#595859]">Revert</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="mb-1 flex items-center justify-between gap-3 text-xs text-[#595859]">
                                            <span x-text="`${item.processed_rows} / ${item.total_rows || '-'}`"></span>
                                            <span x-text="`${item.progress}%`"></span>
                                        </div>
                                        <div class="h-2 rounded-[4px] bg-[#D9D9D9]">
                                            <div class="h-2 rounded-[4px] bg-[#262526]" :style="`width: ${item.progress}%`"></div>
                                        </div>
                                        <p class="mt-2 text-xs text-[#595859]" x-text="`${item.successful_rows} imported - ${item.failed_rows} failed - ${item.duplicate_rows} duplicates`"></p>
                                    </div>
                                </div>
                            @empty
                                <div class="px-5 py-6 text-sm text-[#595859]">No campaign result imports yet.</div>
                            @endforelse
                        </div>
                        <div class="px-5 py-4">
                            {{ $imports->links() }}
                        </div>
                    </article>
                </section>

                <section class="ivr-panel overflow-hidden bg-white">
                    <div class="border-b border-[#D9D9D9] px-5 py-4">
                        <h3 class="text-lg font-semibold text-[#0D0D0D]">Imported call outcomes</h3>
                        <p class="mt-1 text-sm text-[#595859]">
                            Showing latest imported campaign:
                            @if ($latestCampaign)
                                <a href="{{ route('modules.ivr.results.show', $latestCampaign) }}" class="text-[#262526]">{{ $latestCampaign->external_campaign_id }}</a>
                            @else
                                none yet
                            @endif
                        </p>
                        <form method="GET" class="mt-4 grid gap-3 md:grid-cols-3">
                            <select name="outcome" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                                <option value="">All outcomes</option>
                                @foreach (['interested', 'more_info', 'unsubscribe', 'no_input', 'other'] as $outcome)
                                    <option value="{{ $outcome }}" @selected(request('outcome') == $outcome)>{{ ucfirst(str_replace('_', ' ', $outcome)) }}</option>
                                @endforeach
                            </select>
                            <select name="call_status" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                                <option value="">All statuses</option>
                                @foreach (['Answered', 'Missed'] as $status)
                                    <option value="{{ $status }}" @selected(request('call_status') == $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="date" value="{{ request('date') }}" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-[#D9D9D9] text-[#595859]">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Time</th>
                                    <th class="px-5 py-3 font-medium">Campaign</th>
                                    <th class="px-5 py-3 font-medium">Phone</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3 font-medium">DTMF</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($results as $result)
                                    <tr class="border-b border-[#D9D9D9]">
                                        <td class="px-5 py-3">{{ optional($result->call_time)->format('Y-m-d H:i') }}</td>
                                        <td class="px-5 py-3">
                                            @if ($result->campaign)
                                                <a href="{{ route('modules.ivr.results.show', $result->campaign) }}" class="text-[#262526]">
                                                    {{ $result->campaign->external_campaign_id }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">{{ $result->phoneNumber?->normalized_phone }}</td>
                                        <td class="px-5 py-3">{{ $result->call_status }}</td>
                                        <td class="px-5 py-3">{{ $result->dtmf_outcome }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-6 text-[#595859]">No call results found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-4">
                        {{ $results->links() }}
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
