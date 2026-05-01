<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Campaign Results</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">
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
                    <article class="ui-card ui-card-pad">
                        <h3 class="ui-title">Import campaign report</h3>
                        <form method="POST" action="{{ route('modules.ivr.results.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="file" :value="__('Campaign CSV')" />
                                <input id="file" name="file" type="file" class="ui-control mt-1 block w-full">
                                <x-input-error :messages="$errors->get('file')" class="mt-2" />
                            </div>
                            <x-primary-button>Queue Results Import</x-primary-button>
                        </form>
                    </article>

                    <article class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Result import history</h3>
                        </div>
                        <div class="ui-divide max-h-[560px] overflow-y-auto">
                            @forelse ($imports as $import)
                                @php
                                    $campaignReference = data_get($import->summary, 'order_number') ?: pathinfo($import->original_file_name, PATHINFO_FILENAME);
                                    $campaign = $importCampaigns->get($campaignReference);
                                @endphp
                                <div class="px-5 py-4 text-sm" x-data="{ item: get({{ $import->id }}) }">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="break-all font-medium text-theme-primary">{{ $import->original_file_name }}</p>
                                            <p class="capitalize ui-muted" x-text="item.status_label"></p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                            @if ($campaign)
                                                <a href="{{ route('modules.ivr.results.show', $campaign) }}" class="ui-pill">Campaign</a>
                                            @endif

                                            <a href="{{ route('modules.ivr.imports.show', $import) }}" class="ui-pill">Import log</a>

                                            @if (! in_array($import->status, ['pending', 'processing', 'reverted'], true) && $import->reverted_at === null)
                                                <form method="POST" action="{{ route('modules.ivr.results.destroy', $import) }}" onsubmit="return confirm('Revert this campaign import? This will remove its campaign results and recalculate affected numbers.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="ui-pill">Revert</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="mb-1 flex items-center justify-between gap-3 text-xs ui-muted">
                                            <span x-text="`${item.processed_rows} / ${item.total_rows || '-'}`"></span>
                                            <span x-text="`${item.progress}%`"></span>
                                        </div>
                                        <div class="ui-progress">
                                            <div class="ui-progress-bar" :style="`width: ${item.progress}%`"></div>
                                        </div>
                                        <p class="mt-2 text-xs ui-muted" x-text="`${item.successful_rows} imported - ${item.failed_rows} failed - ${item.duplicate_rows} duplicates`"></p>
                                    </div>
                                </div>
                            @empty
                                <div class="ui-empty">No campaign result imports yet.</div>
                            @endforelse
                        </div>
                        <div class="px-5 py-4">
                            {{ $imports->links() }}
                        </div>
                    </article>
                </section>

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <h3 class="ui-title">Imported call outcomes</h3>
                        <p class="mt-1 text-sm ui-muted">
                            Showing latest imported campaign:
                            @if ($latestCampaign)
                                <a href="{{ route('modules.ivr.results.show', $latestCampaign) }}" class="ui-link">{{ $latestCampaign->external_campaign_id }}</a>
                            @else
                                none yet
                            @endif
                        </p>
                        <form method="GET" class="mt-4 grid gap-3 md:grid-cols-3">
                            <select name="outcome" class="ui-control">
                                <option value="">All outcomes</option>
                                @foreach (['interested', 'more_info', 'unsubscribe', 'no_input', 'other'] as $outcome)
                                    <option value="{{ $outcome }}" @selected(request('outcome') == $outcome)>{{ ucfirst(str_replace('_', ' ', $outcome)) }}</option>
                                @endforeach
                            </select>
                            <select name="call_status" class="ui-control">
                                <option value="">All statuses</option>
                                @foreach (['Answered', 'Missed'] as $status)
                                    <option value="{{ $status }}" @selected(request('call_status') == $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="date" value="{{ request('date') }}" class="ui-control">
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Campaign</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>DTMF</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($results as $result)
                                    <tr>
                                        <td>{{ optional($result->call_time)->format('Y-m-d H:i') }}</td>
                                        <td>
                                            @if ($result->campaign)
                                                <a href="{{ route('modules.ivr.results.show', $result->campaign) }}" class="ui-link">
                                                    {{ $result->campaign->external_campaign_id }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $result->phoneNumber?->normalized_phone }}</td>
                                        <td>{{ $result->call_status }}</td>
                                        <td>{{ $result->dtmf_outcome }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="ui-empty">No call results found.</td>
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
