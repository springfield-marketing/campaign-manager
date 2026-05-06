<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Reports</h2>
            <div class="mt-3">@include('whatsapp::partials.section-nav')</div>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @php
                $months = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                ];

                $summaryLabels = [
                    'total_messages' => 'Total messages',
                    'delivered' => 'Delivered',
                    'read' => 'Read',
                    'failed' => 'Failed',
                    'clicked' => 'Clicked',
                ];
            @endphp

            <div class="ui-card ui-card-pad">
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

            <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-5">
                @foreach ($summary as $key => $value)
                    <article class="ui-card ui-card-pad">
                        <p class="text-sm ui-muted">{{ $summaryLabels[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</p>
                        <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($value) }}</p>
                    </article>
                @endforeach
            </div>

            <section class="ui-card mt-6 overflow-hidden">
                <div class="ui-section-head">
                    <h3 class="ui-title">Campaign breakdown</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Started</th>
                                <th>Total</th>
                                <th>Delivered</th>
                                <th>Read</th>
                                <th>Failed</th>
                                <th>Clicked</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($campaignBreakdown as $campaign)
                                <tr>
                                    <td>
                                        <a href="{{ route('modules.whatsapp.campaigns.show', $campaign) }}" class="ui-link">
                                            {{ $campaign->name }}
                                        </a>
                                    </td>
                                    <td>{{ optional($campaign->started_at)->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ number_format($campaign->messages_count) }}</td>
                                    <td>{{ number_format($campaign->delivered_count) }}</td>
                                    <td>{{ number_format($campaign->read_count) }}</td>
                                    <td>{{ number_format($campaign->failed_count) }}</td>
                                    <td>{{ number_format($campaign->clicked_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="ui-empty">No campaign data for this period.</td>
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
