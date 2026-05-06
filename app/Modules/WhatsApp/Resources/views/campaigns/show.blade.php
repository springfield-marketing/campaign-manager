<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">{{ $campaign->name }}</h2>
            <div class="mt-3">@include('whatsapp::partials.section-nav')</div>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="mb-6">
                <a href="{{ route('modules.whatsapp.campaigns.index') }}" class="text-sm ui-link">Back to campaign results</a>
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

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="ui-stat">
                        <p class="ui-stat-label">Total messages</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['total_messages']) }}</p>
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
                        <p class="ui-stat-label">Failed</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['failed_count']) }}</p>
                    </div>
                    <div class="ui-stat">
                        <p class="ui-stat-label">Clicked</p>
                        <p class="ui-stat-value text-2xl">{{ number_format($stats['clicked_count']) }}</p>
                    </div>
                </div>
            </section>

            <section class="ui-card mt-6 overflow-hidden">
                <div class="ui-section-head flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="ui-title">Messages</h3>
                        <p class="mt-1 text-sm ui-muted">All messages sent in this campaign.</p>
                    </div>
                    <a href="{{ route('modules.whatsapp.campaigns.export', $campaign) }}" class="ui-button">
                        Export CSV
                    </a>
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
                                <th>Clicked</th>
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
                                    <td>{{ $message->clicked ? 'Yes' : 'No' }}</td>
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
                                    <td colspan="8" class="ui-empty">No messages found for this campaign.</td>
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
