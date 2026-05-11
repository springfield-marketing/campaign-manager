<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Reports</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
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

            <div class="ui-card ui-card-pad mt-6">
                <form method="GET" class="grid gap-3 md:grid-cols-3">
                    <input type="number" name="year" value="{{ $year }}" class="ui-control">
                    <select name="month" class="ui-control">
                        <option value="">Whole year</option>
                        @foreach ($months as $monthOption => $monthName)
                            <option value="{{ $monthOption }}" @selected((int) $month === $monthOption)>{{ $monthName }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="ui-button">Apply</button>
                </form>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($summary as $label => $value)
                    <article class="ui-card ui-card-pad">
                        <p class="text-sm ui-muted">{{ $summaryLabels[$label] ?? ucwords(str_replace('_', ' ', $label)) }}</p>
                        <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($value) }}</p>
                    </article>
                @endforeach
            </div>

            @if ($monthlyBudget !== null)
                <section class="ui-card ui-card-pad mt-6">
                    <h3 class="ui-title">Monthly budget</h3>
                    <div class="mt-4 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                        <article>
                            <p class="text-sm ui-muted">Quota</p>
                            <p class="mt-1 text-2xl font-semibold text-theme-primary">{{ number_format($monthlyBudget['minutes_quota']) }} min</p>
                        </article>
                        <article>
                            <p class="text-sm ui-muted">Remaining minutes</p>
                            <p class="mt-1 text-2xl font-semibold {{ $monthlyBudget['minutes_remaining'] <= 0 ? 'text-red-500' : 'text-theme-primary' }}">
                                {{ number_format($monthlyBudget['minutes_remaining']) }} min
                            </p>
                        </article>
                        <article>
                            <p class="text-sm ui-muted">Budget / day <span class="font-normal">({{ $monthlyBudget['remaining_working_days'] }} working days left)</span></p>
                            <p class="mt-1 text-2xl font-semibold {{ $monthlyBudget['minutes_remaining'] <= 0 ? 'text-red-500' : 'text-theme-primary' }}">
                                {{ number_format($monthlyBudget['minutes_per_day']) }} min/day
                            </p>
                        </article>
                    </div>
                    @if ($monthlyBudget['minutes_used'] > $monthlyBudget['minutes_quota'])
                        <p class="mt-4 text-sm text-red-500">
                            Quota exceeded by {{ number_format($monthlyBudget['minutes_used'] - $monthlyBudget['minutes_quota']) }} minutes.
                        </p>
                    @endif
                </section>
            @endif

            <section class="ui-card mt-6 overflow-hidden">
                <div class="ui-section-head">
                    <h3 class="ui-title">Campaign breakdown</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Run date</th>
                                <th>Campaign Run time</th>
                                <th>ID</th>
                                <th>Total Calls</th>
                                <th>Leads (1+2)</th>
                                <th>Minutes Used</th>
                                <th>Cost (Gross)</th>
                                <th>Cost (Answered)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($campaignBreakdown as $campaign)
                                @php
                                    $started = $campaign->campaign_started_at ? \Illuminate\Support\Carbon::parse($campaign->campaign_started_at) : null;
                                    $completed = $campaign->campaign_completed_at ? \Illuminate\Support\Carbon::parse($campaign->campaign_completed_at) : null;
                                    $costGross = (float) $campaign->campaign_cost;
                                    $answeredCalls = (int) $campaign->answered_calls;
                                    $unsubscribedCalls = (int) $campaign->unsubscribed_calls;
                                    $costAnswered = $answeredCalls > 0
                                        ? $costGross * max(0, $answeredCalls - $unsubscribedCalls) / $answeredCalls
                                        : 0;
                                @endphp
                                <tr>
                                    <td>
                                        @if ($started)
                                            {{ $started->format('Y-m-d') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($started && $completed)
                                            {{ $started->format('H:i') }} - {{ $completed->format('H:i') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('modules.ivr.results.show', ['campaign' => $campaign->campaign_id ?? $campaign->id]) }}" class="ui-link">
                                            {{ $campaign->external_campaign_id }}
                                        </a>
                                    </td>
                                    <td>{{ number_format($campaign->calls_count) }}</td>
                                    <td>{{ number_format((int) $campaign->leads_count_filtered + (int) $campaign->more_info_count_filtered) }}</td>
                                    <td>{{ number_format($campaign->minutes_used) }}</td>
                                    <td>{{ number_format($costGross, 2) }} AED</td>
                                    <td>{{ number_format($costAnswered, 2) }} AED</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="ui-empty">No campaign data available.</td>
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
