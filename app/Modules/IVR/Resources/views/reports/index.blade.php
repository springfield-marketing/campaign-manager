<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-[#0D0D0D]">Reports</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @php
                $summaryLabels = [
                    'total_calls' => 'Total calls',
                    'answered_calls' => 'Answered calls',
                    'missed_calls' => 'Missed calls',
                    'leads' => 'Pressed 1',
                    'more_info' => 'Pressed 2',
                    'unsubscribed' => 'Unsubscribed',
                    'minutes_consumed' => 'Minutes consumed',
                ];

                $months = [
                    1 => 'January',
                    2 => 'February',
                    3 => 'March',
                    4 => 'April',
                    5 => 'May',
                    6 => 'June',
                    7 => 'July',
                    8 => 'August',
                    9 => 'September',
                    10 => 'October',
                    11 => 'November',
                    12 => 'December',
                ];
            @endphp

            <div class="ivr-panel bg-white p-5">
                <form method="GET" class="grid gap-3 md:grid-cols-3">
                    <input type="number" name="year" value="{{ $year }}" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                    <select name="month" class="rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                        <option value="">Whole year</option>
                        @foreach ($months as $monthOption => $monthName)
                            <option value="{{ $monthOption }}" @selected((int) $month === $monthOption)>{{ $monthName }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="ivr-btn-accent px-3 py-2 text-sm">Apply</button>
                </form>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($summary as $label => $value)
                    <article class="ivr-panel bg-white p-5">
                        <p class="text-sm text-[#595859]">{{ $summaryLabels[$label] ?? ucwords(str_replace('_', ' ', $label)) }}</p>
                        <p class="mt-3 text-3xl font-semibold text-[#0D0D0D]">{{ $value }}</p>
                    </article>
                @endforeach
            </div>

            <section class="mt-6 ivr-panel overflow-hidden bg-white">
                    <div class="border-b border-[#D9D9D9] px-5 py-4">
                        <h3 class="text-lg font-semibold text-[#0D0D0D]">Campaign breakdown</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-[#D9D9D9] text-[#595859]">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Run date</th>
                                    <th class="px-5 py-3 font-medium">Campaign Run time</th>
                                    <th class="px-5 py-3 font-medium">ID</th>
                                    <th class="px-5 py-3 font-medium">Total Calls</th>
                                    <th class="px-5 py-3 font-medium">Leads (1+2)</th>
                                    <th class="px-5 py-3 font-medium">Minutes Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($campaignBreakdown as $campaign)
                                    <tr class="border-b border-[#D9D9D9]">
                                        <td class="px-5 py-3">
                                            @php
                                                $started = $campaign->campaign_started_at ? \Illuminate\Support\Carbon::parse($campaign->campaign_started_at) : null;
                                                $completed = $campaign->campaign_completed_at ? \Illuminate\Support\Carbon::parse($campaign->campaign_completed_at) : null;
                                            @endphp
                                            @if ($started)
                                                {{ $started->format('Y-m-d') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($started && $completed)
                                                {{ $started->format('H:i') }} - {{ $completed->format('H:i') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <a href="{{ route('modules.ivr.results.show', ['campaign' => $campaign->campaign_id ?? $campaign->id]) }}" class="text-[#262526]">
                                                {{ $campaign->external_campaign_id }}
                                            </a>
                                        </td>
                                        <td class="px-5 py-3">{{ $campaign->calls_count }}</td>
                                        <td class="px-5 py-3">{{ (int) $campaign->leads_count_filtered + (int) $campaign->more_info_count_filtered }}</td>
                                        <td class="px-5 py-3">{{ number_format($campaign->minutes_used) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-6 text-[#595859]">No campaign data available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="px-5 py-4">
                        {{ $campaignBreakdown->links() }}
                    </div>
            </section>
        </div>
    </div>
</x-app-layout>
