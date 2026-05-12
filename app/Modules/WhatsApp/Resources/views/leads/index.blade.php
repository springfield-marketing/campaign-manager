<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Leads</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <section class="ui-card overflow-hidden">
                <div class="ui-section-head">
                    <h3 class="ui-title">Potential leads</h3>
                    <p class="mt-1 text-sm ui-muted">Numbers that responded with Quick Reply 1 or 2 (and did not report spam).</p>

                    <form method="GET" class="mt-4 grid gap-3 md:grid-cols-4">
                        <input type="text" name="phone" value="{{ request('phone') }}" placeholder="Phone number" class="ui-control">
                        <input type="text" name="campaign" value="{{ request('campaign') }}" placeholder="Campaign name" class="ui-control">
                        <input type="text" name="reply" value="{{ request('reply') }}" placeholder="Reply text" class="ui-control">
                        <button type="submit" class="ui-button">Filter</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Scheduled</th>
                                <th>Phone</th>
                                <th>Client</th>
                                <th>Campaign</th>
                                <th>Template</th>
                                <th>Reply 1</th>
                                <th>Reply 2</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($leads as $lead)
                                <tr>
                                    <td>{{ optional($lead->scheduled_at)->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if ($lead->phoneNumber)
                                            <a href="{{ route('modules.whatsapp.numbers.show', $lead->phoneNumber) }}" class="ui-link">
                                                {{ $lead->phoneNumber->normalized_phone }}
                                            </a>
                                        @else
                                            {{ $lead->raw_payload['PhoneNumber'] ?? '-' }}
                                        @endif
                                    </td>
                                    <td>{{ $lead->phoneNumber?->client?->full_name ?: '-' }}</td>
                                    <td>
                                        @if ($lead->campaign)
                                            <a href="{{ route('modules.whatsapp.campaigns.show', $lead->campaign) }}" class="ui-link">
                                                {{ $lead->campaign->name }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $lead->template_name ?: '-' }}</td>
                                    <td>{{ $lead->quick_reply_1 ?: '-' }}</td>
                                    <td>{{ $lead->quick_reply_2 ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="ui-empty">No leads found.</td>
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
