<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Reports</h2>
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
            @endphp

            <div class="ui-card ui-card-pad">
                {{-- Quick presets --}}
                <div class="mb-3 flex flex-wrap gap-2 text-sm">
                    <a href="{{ route('modules.whatsapp.reports.index') }}?year={{ now()->year }}&month={{ now()->month }}" class="ui-pill @if((int)$year === now()->year && (int)$month === now()->month) ui-pill-active @endif">This month</a>
                    @php $threeMonthsAgo = now()->subMonths(2); @endphp
                    <a href="{{ route('modules.whatsapp.reports.index') }}?year={{ $threeMonthsAgo->year }}&month={{ $threeMonthsAgo->month }}" class="ui-pill">3 months ago</a>
                    <a href="{{ route('modules.whatsapp.reports.index') }}?year={{ now()->year }}" class="ui-pill @if((int)$year === now()->year && !$month) ui-pill-active @endif">{{ now()->year }} (full year)</a>
                    <a href="{{ route('modules.whatsapp.reports.index') }}?year={{ now()->year - 1 }}" class="ui-pill @if((int)$year === now()->year - 1 && !$month) ui-pill-active @endif">{{ now()->year - 1 }} (full year)</a>
                </div>

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

            <div class="mt-6 grid gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Total dispatched</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($summary['total']) }}</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Sent</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($summary['sent']) }}</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Delivered</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($summary['delivered']) }}</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Read</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($summary['read']) }}</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Replied</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($summary['replied']) }}</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Failed</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($summary['failed']) }}</p>
                </article>
            </div>

            @php
                $deliveryBase = max($summary['total'], 1);
                $readBase     = max($summary['delivered'] + $summary['read'] + $summary['replied'], 1);
                $replyBase    = max($summary['total'], 1);
            @endphp
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Delivery rate</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($rates['delivery_rate'], 1) }}%</p>
                    <p class="mt-1 text-xs ui-muted">{{ number_format($summary['delivered'] + $summary['read'] + $summary['replied']) }} of {{ number_format($summary['total']) }} &middot; Delivered + Read + Replied / Total</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Read rate</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($rates['read_rate'], 1) }}%</p>
                    <p class="mt-1 text-xs ui-muted">{{ number_format($summary['read'] + $summary['replied']) }} of {{ number_format($summary['delivered'] + $summary['read'] + $summary['replied']) }} &middot; Read + Replied / Delivered</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Reply rate</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($rates['reply_rate'], 1) }}%</p>
                    <p class="mt-1 text-xs ui-muted">{{ number_format($summary['replied']) }} of {{ number_format($summary['total']) }} &middot; Replied / Total</p>
                </article>
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
                                <th>Sent</th>
                                <th>Delivered</th>
                                <th>Read</th>
                                <th>Replied</th>
                                <th>Failed</th>
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
                                    <td>{{ number_format($campaign->total_messages) }}</td>
                                    <td>{{ number_format($campaign->sent_count) }}</td>
                                    <td>{{ number_format($campaign->delivered_count) }}</td>
                                    <td>{{ number_format($campaign->read_count) }}</td>
                                    <td>{{ number_format($campaign->replied_count) }}</td>
                                    <td>{{ number_format($campaign->failed_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="ui-empty">No campaign data for this period.</td>
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
