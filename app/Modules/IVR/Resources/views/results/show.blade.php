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
                <h3 class="ui-title">IVR Audio</h3>

                @if ($campaign->audio_file_path)
                    <div class="mt-4">
                        <audio controls class="w-full" src="{{ route('modules.ivr.results.audio', $campaign) }}">
                            Your browser does not support audio playback.
                        </audio>
                        <p class="mt-1 text-xs ui-muted">{{ $campaign->audio_original_name }}</p>
                    </div>
                @endif

                @if ($campaign->audio_script)
                    <div class="mt-4 {{ $campaign->audio_file_path ? 'border-t pt-4' : '' }}">
                        <p class="text-sm font-medium">Script</p>
                        <p class="mt-2 text-sm whitespace-pre-wrap ui-muted">{{ $campaign->audio_script }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('modules.ivr.results.audio.update', $campaign) }}" enctype="multipart/form-data" class="mt-6 space-y-4 border-t pt-4">
                    @csrf
                    <p class="text-sm font-medium">{{ $campaign->audio_file_path ? 'Replace audio' : 'Upload audio' }}</p>

                    @if (session('status'))
                        <div class="ui-alert">{{ session('status') }}</div>
                    @endif

                    <div>
                        <x-input-label for="audio_file" :value="__('Audio file')" />
                        <input id="audio_file" name="audio_file" type="file" accept="audio/*" class="ui-control mt-1 block w-full">
                        <x-input-error :messages="$errors->get('audio_file')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="audio_script" :value="__('Script')" />
                        <textarea id="audio_script" name="audio_script" rows="5" class="ui-control mt-1 block w-full" placeholder="Paste the IVR script here…">{{ old('audio_script', $campaign->audio_script) }}</textarea>
                        <x-input-error :messages="$errors->get('audio_script')" class="mt-2" />
                    </div>
                    <x-primary-button>Save</x-primary-button>
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
                                    <td>{{ $lead->phoneNumber?->normalized_phone }}</td>
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
