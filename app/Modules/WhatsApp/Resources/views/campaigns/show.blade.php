<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">{{ $campaign->name }}</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="mb-6">
                <a href="{{ route('modules.whatsapp.campaigns.index') }}" class="text-sm ui-link">&larr; Back to campaigns</a>
            </div>

            <section class="ui-card ui-card-pad">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm ui-muted">Campaign</p>
                        <h3 class="text-xl font-semibold text-theme-primary">{{ $campaign->name }}</h3>
                    </div>
                    <div class="text-sm ui-muted">
                        {{ optional($campaign->started_at)->format('Y-m-d') ?: 'Date not available' }}
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="ui-stat">
                        <p class="ui-stat-label">Total</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['total_messages']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Sent</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['sent_count']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Delivered</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['delivered_count']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Read</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['read_count']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Replied</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['replied_count']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Failed</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['failed_count']) }}</p>
                    </div>
                </div>

                {{-- Delivery funnel --}}
                @php
                    $total = max($stats['total_messages'], 1);
                    $funnelSteps = [
                        ['label' => 'Sent',      'count' => $stats['sent_count'],      'colour' => 'bg-blue-500'],
                        ['label' => 'Delivered', 'count' => $stats['delivered_count'], 'colour' => 'bg-indigo-500'],
                        ['label' => 'Read',      'count' => $stats['read_count'],      'colour' => 'bg-purple-500'],
                        ['label' => 'Replied',   'count' => $stats['replied_count'],   'colour' => 'bg-theme-accent'],
                    ];
                @endphp
                <div class="mt-6 space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide ui-muted">Delivery funnel</p>
                    @foreach ($funnelSteps as $step)
                        @php $pct = round(($step['count'] / $total) * 100, 1); @endphp
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-20 text-right ui-muted shrink-0">{{ $step['label'] }}</span>
                            <div class="flex-1 rounded bg-theme-subtle h-5 overflow-hidden">
                                <div class="{{ $step['colour'] }} h-full rounded transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="w-28 text-xs ui-muted shrink-0">{{ number_format($step['count']) }} ({{ $pct }}%)</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ui-card mt-6 overflow-hidden">
                <div class="ui-section-head flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="ui-title">Messages</h3>
                        <p class="mt-1 text-sm ui-muted">All messages sent in this campaign.</p>
                    </div>
                    <a href="{{ route('modules.whatsapp.campaigns.export', $campaign) }}" class="ui-button shrink-0">
                        Export CSV
                    </a>
                </div>

                <div class="px-5 pb-4 border-b border-[var(--line)]">
                    <form method="GET" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        <input type="text" name="phone" value="{{ request('phone') }}" placeholder="Search phone number" class="ui-control">
                        <select name="status" class="ui-control">
                            <option value="">All statuses</option>
                            @foreach (['SENT', 'DELIVERED', 'READ', 'REPLIED', 'FAILED'] as $s)
                                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                        <div class="flex gap-2">
                            <button type="submit" class="ui-button">Filter</button>
                            <a href="{{ route('modules.whatsapp.campaigns.show', $campaign) }}" class="ui-button">Clear</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Scheduled</th>
                                <th>Phone</th>
                                <th>Name</th>
                                <th>Template</th>
                                <th>Status</th>
                                <th>Quick Reply</th>
                                <th>Failure Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($messages as $message)
                                <tr>
                                    <td>{{ optional($message->scheduled_at)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $message->phoneNumber?->normalized_phone ?: ($message->raw_payload['PhoneNumber'] ?? '-') }}</td>
                                    <td>{{ $message->phoneNumber?->client?->full_name ?: '-' }}</td>
                                    <td>{{ $message->template_name ?: '-' }}</td>
                                    <td>{{ $message->delivery_status ?: '-' }}</td>
                                    <td>
                                        @if ($message->quick_reply_1)
                                            {{ $message->quick_reply_1 }}
                                            @if ($message->quick_reply_2) / {{ $message->quick_reply_2 }} @endif
                                            @if ($message->quick_reply_3) / {{ $message->quick_reply_3 }} @endif
                                        @else
                                            &ndash;
                                        @endif
                                    </td>
                                    <td>{{ $message->failure_reason ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="ui-empty">No messages found for this campaign.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4">
                    {{ $messages->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
