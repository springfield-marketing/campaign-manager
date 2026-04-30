<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-[#0D0D0D]">Campaign {{ $campaign->external_campaign_id }}</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('modules.ivr.results.index') }}" class="text-sm text-[#595859]">Back to campaign results</a>
            </div>

            <section class="ivr-panel bg-white p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm text-[#595859]">Campaign</p>
                        <h3 class="text-xl font-semibold text-[#0D0D0D]">{{ $campaign->external_campaign_id }}</h3>
                    </div>
                    <div class="text-sm text-[#595859]">
                        {{ optional($campaign->started_at)->format('Y-m-d H:i') ?: 'Start not available' }}
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-[4px] border border-[#D9D9D9] p-4">
                        <p class="text-xs uppercase tracking-wide text-[#595859]">Total calls</p>
                        <p class="mt-2 text-2xl font-semibold text-[#0D0D0D]">{{ number_format($stats['total_calls']) }}</p>
                    </div>
                    <div class="rounded-[4px] border border-[#D9D9D9] p-4">
                        <p class="text-xs uppercase tracking-wide text-[#595859]">Answered</p>
                        <p class="mt-2 text-2xl font-semibold text-[#0D0D0D]">{{ number_format($stats['answered_calls']) }}</p>
                    </div>
                    <div class="rounded-[4px] border border-[#D9D9D9] p-4">
                        <p class="text-xs uppercase tracking-wide text-[#595859]">Missed</p>
                        <p class="mt-2 text-2xl font-semibold text-[#0D0D0D]">{{ number_format($stats['missed_calls']) }}</p>
                    </div>
                    <div class="rounded-[4px] border border-[#D9D9D9] p-4">
                        <p class="text-xs uppercase tracking-wide text-[#595859]">Time consumed</p>
                        <p class="mt-2 text-2xl font-semibold text-[#0D0D0D]">{{ number_format($stats['time_consumed_minutes']) }} min</p>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-[4px] border border-[#D9D9D9] p-4">
                        <p class="text-xs uppercase tracking-wide text-[#595859]">Leads</p>
                        <p class="mt-2 text-xl font-semibold text-[#0D0D0D]">{{ number_format($stats['leads_count']) }}</p>
                    </div>
                    <div class="rounded-[4px] border border-[#D9D9D9] p-4">
                        <p class="text-xs uppercase tracking-wide text-[#595859]">More info</p>
                        <p class="mt-2 text-xl font-semibold text-[#0D0D0D]">{{ number_format($stats['more_info_count']) }}</p>
                    </div>
                    <div class="rounded-[4px] border border-[#D9D9D9] p-4">
                        <p class="text-xs uppercase tracking-wide text-[#595859]">Unsubscribed</p>
                        <p class="mt-2 text-xl font-semibold text-[#0D0D0D]">{{ number_format($stats['unsubscribed_count']) }}</p>
                    </div>
                </div>
            </section>

            <section class="ivr-panel mt-6 overflow-hidden bg-white">
                <div class="flex flex-col gap-3 border-b border-[#D9D9D9] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-[#0D0D0D]">Leads</h3>
                        <p class="mt-1 text-sm text-[#595859]">People who pressed 1 and are marked as interested.</p>
                    </div>
                    <a href="{{ route('modules.ivr.results.leads.export', $campaign) }}" class="ivr-btn-accent inline-flex items-center justify-center px-3 py-2 text-sm">
                        Export leads
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-[#D9D9D9] text-[#595859]">
                            <tr>
                                <th class="px-5 py-3 font-medium">Time</th>
                                <th class="px-5 py-3 font-medium">Name</th>
                                <th class="px-5 py-3 font-medium">Phone</th>
                                <th class="px-5 py-3 font-medium">Email</th>
                                <th class="px-5 py-3 font-medium">City</th>
                                <th class="px-5 py-3 font-medium">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($leads as $lead)
                                <tr class="border-b border-[#D9D9D9]">
                                    <td class="px-5 py-3">{{ optional($lead->call_time)->format('Y-m-d H:i') }}</td>
                                    <td class="px-5 py-3">{{ $lead->phoneNumber?->client?->full_name ?: '-' }}</td>
                                    <td class="px-5 py-3">{{ $lead->phoneNumber?->normalized_phone }}</td>
                                    <td class="px-5 py-3">{{ $lead->phoneNumber?->client?->email ?: '-' }}</td>
                                    <td class="px-5 py-3">{{ $lead->phoneNumber?->client?->city ?: '-' }}</td>
                                    <td class="px-5 py-3">{{ gmdate('H:i:s', $lead->total_duration_seconds) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-6 text-[#595859]">No leads found for this campaign.</td>
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
