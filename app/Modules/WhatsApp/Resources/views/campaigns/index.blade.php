<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Campaigns</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6">

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <div>
                            <h3 class="ui-title">Campaigns</h3>
                            <p class="mt-1 text-sm ui-muted">
                                To import new campaign results, go to
                                <a href="{{ route('modules.whatsapp.imports.index') }}?tab=campaign-results" class="ui-link">Imports &rarr; Campaign Results</a>.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Campaign Name</th>
                                    <th>Started</th>
                                    <th>Total</th>
                                    <th>Sent</th>
                                    <th>Delivered</th>
                                    <th>Read</th>
                                    <th>Replied</th>
                                    <th>Failed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($campaigns as $campaign)
                                    <tr
                                        class="cursor-pointer hover:bg-theme-subtle transition-colors"
                                        onclick="window.location.href='{{ route('modules.whatsapp.campaigns.show', $campaign) }}'"
                                    >
                                        <td class="font-medium">{{ $campaign->name }}</td>
                                        <td>{{ optional($campaign->started_at)->format('Y-m-d') ?: '-' }}</td>
                                        <td>{{ number_format($campaign->total_messages) }}</td>
                                        <td>{{ number_format($campaign->sent_count) }}</td>
                                        <td>{{ number_format($campaign->delivered_count) }}</td>
                                        <td>{{ number_format($campaign->read_count) }}</td>
                                        <td>{{ number_format($campaign->replied_count) }}</td>
                                        <td>{{ number_format($campaign->failed_count) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="ui-empty">No campaigns imported yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-4">
                        {{ $campaigns->links() }}
                    </div>
                </section>

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <h3 class="ui-title">
                            Imported messages
                            @if ($latestCampaign)
                                &mdash; <span class="font-normal text-sm ui-muted">{{ $latestCampaign->name }}</span>
                            @endif
                        </h3>

                        @if ($latestCampaign)
                            <p class="mt-1 text-sm ui-muted">
                                Showing latest imported campaign.
                                <a href="{{ route('modules.whatsapp.campaigns.show', $latestCampaign) }}" class="ui-link">View full campaign &rarr;</a>
                            </p>
                        @endif

                        <form method="GET" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                            <input type="text" name="phone" value="{{ request('phone') }}" placeholder="Phone number" class="ui-control">
                            <select name="status" class="ui-control">
                                <option value="">All statuses</option>
                                @foreach (['DELIVERED', 'READ', 'REPLIED', 'SENT', 'FAILED'] as $status)
                                    <option value="{{ $status }}" @selected(request('status') == $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="template" value="{{ request('template') }}" placeholder="Template name" class="ui-control">
                            <input type="date" name="date" value="{{ request('date') }}" class="ui-control">
                            <button type="submit" class="ui-button">Filter</button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Scheduled</th>
                                    <th>Phone</th>
                                    <th>Template</th>
                                    <th>Status</th>
                                    <th>Quick Reply</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($messages as $message)
                                    <tr>
                                        <td>{{ optional($message->scheduled_at)->format('Y-m-d H:i') }}</td>
                                        <td>{{ $message->phoneNumber?->normalized_phone ?: $message->raw_payload['PhoneNumber'] ?? '-' }}</td>
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
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="ui-empty">No messages found.</td>
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
    </div>
</x-app-layout>
