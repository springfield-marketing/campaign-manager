<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Number History</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">
                <section class="ui-card ui-card-pad">
                    <h3 class="ui-title">{{ $number->normalized_phone }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="ui-muted">Client</dt>
                            <dd class="ui-strong">{{ $number->client?->full_name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">City</dt>
                            <dd class="ui-strong">{{ $number->client?->city ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Usage status</dt>
                            <dd class="ui-strong">{{ ucfirst($number->usage_status) }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Cooldown until</dt>
                            <dd class="ui-strong">{{ optional($number->cooldown_until)->format('Y-m-d H:i') ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Unsubscribed</dt>
                            <dd class="ui-strong">{{ optional($number->unsubscribed_at)->format('Y-m-d H:i') ?: 'No' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="space-y-6">
                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="ui-title">Client numbers</h3>
                                    <p class="mt-1 text-sm ui-muted">
                                        @if ($number->client)
                                            {{ $number->client->phoneNumbers->count() }} number{{ $number->client->phoneNumbers->count() === 1 ? '' : 's' }} linked to this client.
                                        @else
                                            This number is not linked to a client.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        @if ($number->client)
                            <div class="overflow-x-auto">
                                <table class="ui-table">
                                    <thead>
                                        <tr>
                                            <th>Phone</th>
                                            <th>Label</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Uses</th>
                                            <th>Last called</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($number->client->phoneNumbers as $clientNumber)
                                            <tr>
                                                <td>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        @if ($clientNumber->id === $number->id)
                                                            <span class="ui-pill ui-pill-active">Current</span>
                                                        @endif

                                                        @if ($clientNumber->is_primary)
                                                            <span class="ui-pill">Primary</span>
                                                        @endif

                                                        <a href="{{ route('modules.ivr.numbers.show', $clientNumber) }}" class="ui-link">
                                                            {{ $clientNumber->normalized_phone }}
                                                        </a>
                                                    </div>
                                                </td>
                                                <td>{{ $clientNumber->label ?: '-' }}</td>
                                                <td>{{ $clientNumber->priority }}</td>
                                                <td>{{ ucfirst($clientNumber->usage_status) }}</td>
                                                <td>{{ $clientNumber->ivr_use_count }}</td>
                                                <td>{{ optional($clientNumber->last_called_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="ui-empty">No linked client numbers available.</div>
                        @endif
                    </div>

                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Call history</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Campaign</th>
                                        <th>Status</th>
                                        <th>Outcome</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($number->ivrCallRecords as $call)
                                        <tr>
                                            <td>{{ optional($call->call_time)->format('Y-m-d H:i') }}</td>
                                            <td>{{ $call->campaign?->external_campaign_id }}</td>
                                            <td>{{ $call->call_status }}</td>
                                            <td>{{ $call->dtmf_outcome ?: '-' }}</td>
                                            <td>{{ $call->total_duration_seconds }}s</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="ui-empty">No call history available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Source history</h3>
                        </div>
                        <div class="ui-divide">
                            @forelse ($number->sources as $source)
                                <div class="px-5 py-4 text-sm">
                                    <p class="font-medium ui-strong">{{ $source->source_name ?: $source->source_type }}</p>
                                    <p class="ui-muted">{{ $source->source_type }} - {{ $source->created_at->format('Y-m-d H:i') }}</p>
                                </div>
                            @empty
                                <div class="ui-empty">No source history available.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
