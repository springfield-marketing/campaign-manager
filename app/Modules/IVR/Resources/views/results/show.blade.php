<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Campaign {{ $campaign->external_campaign_id }}</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="mb-6">
                <a href="{{ route('modules.ivr.results.index') }}" class="text-sm ui-link">Back to campaign results</a>
            </div>

            <section class="ui-card ui-card-pad">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm ui-muted">Campaign</p>
                        <h3 class="text-xl font-semibold text-theme-primary">{{ $campaign->external_campaign_id }}</h3>
                    </div>
                    <div class="text-sm ui-muted">
                        {{ optional($campaign->started_at)->format('Y-m-d H:i') ?: 'Start not available' }}
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="ui-stat">
                        <p class="ui-stat-label">Total calls</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['total_calls']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Answered</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['answered_calls']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Missed</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['missed_calls']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Time consumed</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['time_consumed_minutes']) }} min</p>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="ui-stat">
                        <p class="ui-stat-label">Leads</p>
                        <p class="ui-stat-value text-xl">{{ number_format($stats['leads_count']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">More info</p>
                        <p class="ui-stat-value text-xl">{{ number_format($stats['more_info_count']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Unsubscribed</p>
                        <p class="ui-stat-value text-xl">{{ number_format($stats['unsubscribed_count']) }}</p>
                    </div>
                </div>
            </section>

            <section class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title">IVR Script</h3>

                @if (session('status'))
                    <div class="ui-alert mt-4">{{ session('status') }}</div>
                @endif

                @php
                    $activeScript = $campaign->script;
                    $hasLegacyAudio = ! $activeScript && ($campaign->audio_file_path || $campaign->audio_script);
                @endphp

                @if ($activeScript)
                    <div class="mt-4">
                        <p class="text-sm font-medium text-theme-primary">{{ $activeScript->name }}</p>

                        @if ($activeScript->audio_file_path)
                            <audio controls class="mt-3 w-full" src="{{ route('modules.ivr.results.audio', $campaign) }}">
                                Your browser does not support audio playback.
                            </audio>
                            <p class="mt-1 text-xs ui-muted">{{ $activeScript->audio_original_name }}</p>
                        @endif

                        @if ($activeScript->audio_script)
                            <p class="mt-4 whitespace-pre-wrap text-sm ui-muted">{{ $activeScript->audio_script }}</p>
                        @endif
                    </div>
                @elseif ($hasLegacyAudio)
                    <div class="mt-4">
                        <p class="text-xs ui-muted">Legacy audio — not linked to a script in the library.</p>

                        @if ($campaign->audio_file_path)
                            <audio controls class="mt-3 w-full" src="{{ route('modules.ivr.results.audio', $campaign) }}">
                                Your browser does not support audio playback.
                            </audio>
                            <p class="mt-1 text-xs ui-muted">{{ $campaign->audio_original_name }}</p>
                        @endif

                        @if ($campaign->audio_script)
                            <p class="mt-4 whitespace-pre-wrap text-sm ui-muted">{{ $campaign->audio_script }}</p>
                        @endif
                    </div>
                @else
                    <p class="mt-4 text-sm ui-muted">No script assigned.</p>
                @endif

                <form method="POST" action="{{ route('modules.ivr.results.script.assign', $campaign) }}" class="mt-6 border-t pt-4">
                    @csrf
                    @method('PATCH')
                    <x-input-label for="ivr_script_id" :value="__('Assign script')" />
                    <div class="mt-1 flex gap-3">
                        <select id="ivr_script_id" name="ivr_script_id" class="ui-control flex-1">
                            <option value="">— No script —</option>
                            @foreach ($scripts as $script)
                                <option value="{{ $script->id }}" @selected($campaign->ivr_script_id == $script->id)>{{ $script->name }}</option>
                            @endforeach
                        </select>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                    @if ($scripts->isEmpty())
                        <p class="mt-1 text-xs ui-muted">No scripts yet. <a href="{{ route('modules.ivr.scripts.index') }}" class="ui-link">Upload one in Scripts.</a></p>
                    @endif
                </form>
            </section>

            <section class="ui-card mt-6 overflow-hidden">
                <div class="ui-section-head flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="ui-title">Leads</h3>
                        <p class="mt-1 text-sm ui-muted">People who pressed 1 and are marked as interested.</p>
                    </div>
                    <a href="{{ route('modules.ivr.results.leads.export', $campaign) }}" class="ui-button">
                        Export leads
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Emirate</th>
                                <th>Source</th>
                                <th>Outcome</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($leads as $lead)
                                <tr>
                                    <td>{{ optional($lead->call_time)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $lead->phoneNumber?->client?->full_name ?: '-' }}</td>
                                    <td>
                                        @if ($lead->phoneNumber)
                                            <a href="{{ route('modules.ivr.numbers.show', $lead->phoneNumber) }}" class="ui-link">{{ $lead->phoneNumber->normalized_phone }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $lead->phoneNumber?->client?->email ?: '-' }}</td>
                                    <td>{{ $lead->phoneNumber?->client?->region?->name ?: '-' }}</td>
                                    <td>{{ $lead->phoneNumber?->last_source_name ?: '-' }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $lead->dtmf_outcome)) }}</td>
                                    <td>{{ gmdate('H:i:s', $lead->total_duration_seconds) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="ui-empty">No leads found for this campaign.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4">
                    {{ $leads->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
