<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Number History</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">{{ session('status') }}</div>
            @endif

            @php
                $isUnsubscribed = $number->suppressions->contains(
                    fn ($s) => $s->channel === 'ivr'
                        && $s->reason === 'customer_unsubscribed'
                        && $s->released_at === null
                );
            @endphp

            <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">
                <section class="ui-card ui-card-pad">
                    <h3 class="ui-title">{{ $number->normalized_phone }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="ui-muted">Client</dt>
                            <dd class="ui-strong">{{ $number->client?->full_name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Emirate</dt>
                            <dd class="ui-strong">{{ $number->client?->region?->name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Usage status</dt>
                            <dd class="ui-strong">{{ ucfirst($number->ivrProfile?->usage_status ?? 'active') }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Cooldown until</dt>
                            <dd class="ui-strong">{{ optional($number->ivrProfile?->cooldown_until)->format('Y-m-d H:i') ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Unsubscribed</dt>
                            <dd class="ui-strong">{{ optional($number->unsubscribed_at)->format('Y-m-d H:i') ?: 'No' }}</dd>
                        </div>
                    </dl>

                    <div style="margin-top: 24px; border-top: 1px solid var(--line); padding-top: 24px;">
                        @if ($isUnsubscribed)
                            <form method="POST" action="{{ route('modules.ivr.numbers.unsuppress', $number) }}"
                                  onsubmit="return confirm('Remove unsubscribe for this number?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-button-subtle text-sm">Remove unsubscribe</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('modules.ivr.numbers.suppress', $number) }}"
                                  onsubmit="return confirm('Mark this number as unsubscribed? It will be excluded from future campaigns.');">
                                @csrf
                                <button type="submit" class="ui-button-subtle text-sm text-red-600 hover:text-red-700" style="margin-top: 20px;">Mark as unsubscribed</button>
                            </form>
                        @endif
                    </div>
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
                                                <td>{{ ucfirst($clientNumber->ivrProfile?->usage_status ?? 'active') }}</td>
                                                <td>{{ $clientNumber->ivr_use_count }}</td>
                                                <td>{{ optional($clientNumber->ivrProfile?->last_called_at)->format('Y-m-d H:i') ?: '-' }}</td>
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

                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Suppression history</h3>
                        </div>
                        @forelse ($number->suppressions as $suppression)
                            @php
                                $ctx = $suppression->context ?? [];
                                $isActive = $suppression->released_at === null;
                            @endphp
                            <div class="border-b border-[var(--line)] px-5 py-4 text-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium ui-strong capitalize">{{ str_replace('_', ' ', $suppression->reason) }}</p>
                                        <p class="mt-1 ui-muted">
                                            {{ ucfirst($suppression->channel ?? 'all channels') }}
                                            &mdash; {{ \Illuminate\Support\Carbon::parse($suppression->suppressed_at)->format('Y-m-d H:i') }}
                                        </p>

                                        @if ($ctx['source_file'] ?? null)
                                            <p class="mt-1 text-xs ui-muted">File: {{ $ctx['source_file'] }}</p>
                                        @endif
                                        @if ($ctx['campaign_id'] ?? null)
                                            <p class="mt-1 text-xs ui-muted">Campaign: {{ $ctx['campaign_id'] }}</p>
                                        @endif
                                        @if (($ctx['source'] ?? null) === 'manual')
                                            <p class="mt-1 text-xs ui-muted">Added manually</p>
                                        @endif
                                        @if ($ctx['row_number'] ?? null)
                                            <p class="mt-1 text-xs ui-muted">Row {{ $ctx['row_number'] }} in import file</p>
                                        @endif
                                        @if ($ctx['name'] ?? null)
                                            <p class="mt-1 text-xs ui-muted">Name in file: {{ $ctx['name'] }}</p>
                                        @endif

                                        @if ($suppression->released_at)
                                            <p class="mt-1 text-xs text-green-600">Released {{ \Illuminate\Support\Carbon::parse($suppression->released_at)->format('Y-m-d H:i') }}</p>
                                        @endif
                                    </div>
                                    @if ($isActive)
                                        <span class="ui-pill shrink-0">Active</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="ui-empty">No suppression history.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
