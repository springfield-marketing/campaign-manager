<x-filament-panels::page>
    @php
        $data = $this->getReportData();
        $summary = $data['summary'];
        $monthlyBudget = $data['monthlyBudget'];
        $campaignBreakdown = $data['campaignBreakdown'];

        $summaryLabels = [
            'total_calls'       => 'Total Calls',
            'answered_calls'    => 'Answered Calls',
            'missed_calls'      => 'Missed Calls',
            'leads'             => 'Leads (Interested)',
            'more_info'         => 'More Info',
            'unsubscribed'      => 'Unsubscribed',
            'minutes_consumed'  => 'Minutes Consumed',
        ];
    @endphp

    {{-- Filters --}}
    <x-filament::section>
        <div class="flex items-end gap-4">
            {{ $this->form }}
            <x-filament::button wire:click="apply" color="primary" class="mb-1">Apply</x-filament::button>
        </div>
    </x-filament::section>

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-7">
        @foreach ($summary as $key => $value)
            <x-filament::section>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $summaryLabels[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($value) }}</p>
            </x-filament::section>
        @endforeach
    </div>

    {{-- Monthly budget (current month only) --}}
    @if ($monthlyBudget !== null)
        <x-filament::section heading="Monthly Budget">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div>
                    <p class="text-xs text-gray-500">Quota</p>
                    <p class="mt-1 text-xl font-semibold">{{ number_format($monthlyBudget['minutes_quota']) }} min</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Remaining</p>
                    <p class="mt-1 text-xl font-semibold {{ $monthlyBudget['minutes_remaining'] <= 0 ? 'text-red-500' : '' }}">
                        {{ number_format($monthlyBudget['minutes_remaining']) }} min
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Budget / day <span class="font-normal">({{ $monthlyBudget['remaining_working_days'] }} working days left)</span></p>
                    <p class="mt-1 text-xl font-semibold {{ $monthlyBudget['minutes_remaining'] <= 0 ? 'text-red-500' : '' }}">
                        {{ number_format($monthlyBudget['minutes_per_day']) }} min/day
                    </p>
                </div>
            </div>
            @if ($monthlyBudget['minutes_used'] > $monthlyBudget['minutes_quota'])
                <div class="mt-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    Quota exceeded by {{ number_format($monthlyBudget['minutes_used'] - $monthlyBudget['minutes_quota']) }} minutes. You are being billed at the over-quota rate.
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- Campaign breakdown --}}
    <x-filament::section heading="Campaign Breakdown">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-3 py-2 font-medium text-gray-500">Run Date</th>
                        <th class="px-3 py-2 font-medium text-gray-500">Call Window</th>
                        <th class="px-3 py-2 font-medium text-gray-500">Campaign ID</th>
                        <th class="px-3 py-2 font-medium text-gray-500 text-right">Total Calls</th>
                        <th class="px-3 py-2 font-medium text-gray-500 text-right">Leads</th>
                        <th class="px-3 py-2 font-medium text-gray-500 text-right">Minutes</th>
                        <th class="px-3 py-2 font-medium text-gray-500 text-right" title="Total cost for all calls">Cost (total)</th>
                        <th class="px-3 py-2 font-medium text-gray-500 text-right" title="Cost for answered calls minus unsubscribes">Cost (answered)</th>
                        <th class="px-3 py-2 font-medium text-gray-500 text-right" title="Cost per lead">CPL</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totals = ['calls' => 0, 'leads' => 0, 'minutes' => 0, 'costGross' => 0.0, 'costAnswered' => 0.0]; @endphp
                    @forelse ($campaignBreakdown as $row)
                        @php
                            $started   = $row->campaign_started_at   ? \Carbon\Carbon::parse($row->campaign_started_at)   : null;
                            $completed = $row->campaign_completed_at ? \Carbon\Carbon::parse($row->campaign_completed_at) : null;
                            $costGross = (float) $row->campaign_cost;
                            $answered  = (int) $row->answered_calls;
                            $unsubs    = (int) $row->unsubscribed_calls;
                            $costAnswered = $answered > 0 ? $costGross * max(0, $answered - $unsubs) / $answered : 0;
                            $leads = (int) $row->leads_count_filtered + (int) $row->more_info_count_filtered;
                            $cpl   = $leads > 0 ? $costAnswered / $leads : null;
                            $totals['calls']       += (int) $row->calls_count;
                            $totals['leads']       += $leads;
                            $totals['minutes']     += (int) $row->minutes_used;
                            $totals['costGross']   += $costGross;
                            $totals['costAnswered'] += $costAnswered;
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $started?->format('d M Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                {{ ($started && $completed) ? $started->format('H:i').' – '.$completed->format('H:i') : '—' }}
                            </td>
                            <td class="px-3 py-2 font-medium text-primary-600">{{ $row->external_campaign_id }}</td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($row->calls_count) }}</td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($leads) }}</td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($row->minutes_used) }}</td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($costGross, 2) }} AED</td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ number_format($costAnswered, 2) }} AED</td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $cpl !== null ? number_format($cpl, 2).' AED' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-6 text-center text-gray-400">No campaign data for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($campaignBreakdown->isNotEmpty())
                    @php $totalCpl = $totals['leads'] > 0 ? $totals['costAnswered'] / $totals['leads'] : null; @endphp
                    <tfoot>
                        <tr class="bg-gray-50 dark:bg-gray-800 font-semibold">
                            <td colspan="3" class="px-3 py-2 text-sm text-gray-500">Page total</td>
                            <td class="px-3 py-2 text-right text-sm">{{ number_format($totals['calls']) }}</td>
                            <td class="px-3 py-2 text-right text-sm">{{ number_format($totals['leads']) }}</td>
                            <td class="px-3 py-2 text-right text-sm">{{ number_format($totals['minutes']) }}</td>
                            <td class="px-3 py-2 text-right text-sm">{{ number_format($totals['costGross'], 2) }} AED</td>
                            <td class="px-3 py-2 text-right text-sm">{{ number_format($totals['costAnswered'], 2) }} AED</td>
                            <td class="px-3 py-2 text-right text-sm">{{ $totalCpl !== null ? number_format($totalCpl, 2).' AED' : '—' }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($campaignBreakdown->hasPages())
            <div class="mt-4">
                {{ $campaignBreakdown->links() }}
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
