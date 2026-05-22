<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">IVR Settings</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mt-6">
                    {{ session('status') }}
                </div>
            @endif

            <section class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title">Monthly quota & pricing</h3>
                <p class="mt-2 text-sm ui-muted">
                    These settings are used to calculate costs on the reports page.
                </p>

                <form method="POST" action="{{ route('modules.ivr.settings.update') }}" class="mt-6 grid gap-6 md:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="ui-label" for="monthly_minutes_quota">Monthly minutes quota</label>
                        <input
                            id="monthly_minutes_quota"
                            name="monthly_minutes_quota"
                            type="number"
                            min="1"
                            value="{{ old('monthly_minutes_quota', $settings->monthly_minutes_quota) }}"
                            class="ui-control mt-1 block w-full"
                        >
                        <x-input-error :messages="$errors->get('monthly_minutes_quota')" class="mt-2" />
                    </div>

                    <div>
                        <label class="ui-label" for="price_per_minute_under">Price per minute (under quota) — AED</label>
                        <input
                            id="price_per_minute_under"
                            name="price_per_minute_under"
                            type="number"
                            step="0.0001"
                            min="0"
                            value="{{ old('price_per_minute_under', number_format((float) $settings->price_per_minute_under, 4, '.', '')) }}"
                            class="ui-control mt-1 block w-full"
                        >
                        <x-input-error :messages="$errors->get('price_per_minute_under')" class="mt-2" />
                    </div>

                    <div>
                        <label class="ui-label" for="price_per_minute_over">Price per minute (over quota) — AED</label>
                        <input
                            id="price_per_minute_over"
                            name="price_per_minute_over"
                            type="number"
                            step="0.0001"
                            min="0"
                            value="{{ old('price_per_minute_over', number_format((float) $settings->price_per_minute_over, 4, '.', '')) }}"
                            class="ui-control mt-1 block w-full"
                        >
                        <x-input-error :messages="$errors->get('price_per_minute_over')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <hr class="border-[var(--line)]">
                        <h4 class="ui-label mt-6">Cooldown periods</h4>
                        <p class="mt-1 text-sm ui-muted">How long a number is held back from the next campaign after a call.</p>
                    </div>

                    <div>
                        <label class="ui-label" for="cooldown_answered_days">Answered call cooldown — days</label>
                        <input
                            id="cooldown_answered_days"
                            name="cooldown_answered_days"
                            type="number"
                            min="1"
                            max="365"
                            value="{{ old('cooldown_answered_days', $settings->cooldown_answered_days) }}"
                            class="ui-control mt-1 block w-full"
                        >
                        <x-input-error :messages="$errors->get('cooldown_answered_days')" class="mt-2" />
                    </div>

                    <div>
                        <label class="ui-label" for="cooldown_missed_days">Missed call cooldown — days</label>
                        <input
                            id="cooldown_missed_days"
                            name="cooldown_missed_days"
                            type="number"
                            min="1"
                            max="365"
                            value="{{ old('cooldown_missed_days', $settings->cooldown_missed_days) }}"
                            class="ui-control mt-1 block w-full"
                        >
                        <x-input-error :messages="$errors->get('cooldown_missed_days')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" class="ui-button">Save settings</button>
                    </div>
                </form>
            </section>

            <section class="ui-card ui-card-pad mt-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="ui-title">Central database export</h3>
                        <p class="mt-2 text-sm ui-muted">
                            Create a full Excel workbook of the business database for safekeeping or migration.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('modules.ivr.settings.database-export.store') }}">
                        @csrf
                        <button type="submit" class="ui-button">Start Excel export</button>
                    </form>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Size</th>
                                <th>Requested by</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($databaseExports as $export)
                                <tr>
                                    <td>{{ $export->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="capitalize">{{ str_replace('_', ' ', $export->status) }}</td>
                                    <td>
                                        {{ number_format($export->processed_rows) }} / {{ number_format($export->total_rows) }}
                                        <span class="ui-muted">({{ $export->progressPercent() }}%)</span>
                                        @if ($export->status === 'failed' && $export->error_message)
                                            <div class="mt-1 text-xs text-red-700">{{ $export->error_message }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $export->file_size ? number_format($export->file_size / 1024 / 1024, 2).' MB' : '-' }}
                                    </td>
                                    <td>{{ $export->requester?->name ?: '-' }}</td>
                                    <td class="text-right">
                                        @if ($export->status === 'completed')
                                            <a href="{{ route('modules.ivr.settings.database-export.download', $export) }}" class="ui-link">
                                                Download
                                            </a>
                                        @elseif (in_array($export->status, ['pending', 'processing'], true))
                                            <span class="ui-muted">Refresh to update</span>
                                        @else
                                            <span class="ui-muted">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="ui-empty">No database exports yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title">How number eligibility works</h3>
                <p class="mt-2 text-sm ui-muted">
                    This explains how the system decides whether a number can be dialled in a campaign. Every number has one of four statuses.
                </p>

                <div class="mt-6 space-y-6 text-sm">
                    <div>
                        <p class="font-semibold ui-strong">Active</p>
                        <p class="mt-1 ui-muted">The number is eligible to be dialled. It has fewer than {{ config('ivr.eligibility.inactive_after_uses', 3) }} total calls, is not in cooldown, and is not suppressed or dead.</p>
                    </div>

                    <div>
                        <p class="font-semibold ui-strong">Inactive (cooldown)</p>
                        <p class="mt-1 ui-muted">The number is temporarily held back. This happens when:</p>
                        <ul class="mt-2 list-disc pl-5 ui-muted space-y-1">
                            <li>It has been called {{ config('ivr.eligibility.inactive_after_uses', 3) }} or more times in total, <strong>or</strong></li>
                            <li>It is within its cooldown window — {{ $settings->cooldown_answered_days }} days after an answered call, or {{ $settings->cooldown_missed_days }} {{ $settings->cooldown_missed_days === 1 ? 'day' : 'days' }} after a missed/unanswered call.</li>
                        </ul>
                        <p class="mt-2 ui-muted">Once the cooldown window passes, the number becomes active again (if it hasn't reached the dead threshold).</p>
                    </div>

                    <div>
                        <p class="font-semibold ui-strong">Dead</p>
                        <p class="mt-1 ui-muted">The number is permanently removed from campaign eligibility. This happens when the last {{ config('ivr.eligibility.dead_after_uses', 5) }} consecutive calls were all missed or unanswered (i.e. no answered call in the most recent {{ config('ivr.eligibility.dead_after_uses', 5) }} attempts). A single answered call resets the consecutive miss counter.</p>
                    </div>

                    <div>
                        <p class="font-semibold ui-strong">Unsubscribed</p>
                        <p class="mt-1 ui-muted">The number was explicitly marked as unsubscribed — either via an unsubscriber import or manually from the number history page. Unsubscribed numbers are always dead regardless of call history. The suppression can be removed from the number history page to restore eligibility.</p>
                    </div>

                    <div class="border-t border-[var(--line)] pt-4">
                        <p class="font-semibold ui-strong">When eligibility is recalculated</p>
                        <p class="mt-1 ui-muted">Status is recalculated automatically after each campaign result import, after a manual suppress/unsuppress action, and when the <code class="bg-[var(--surface-alt)] px-1 rounded text-xs">ivr:reanalyse-numbers</code> command is run. Changing cooldown settings here does <em>not</em> retroactively update existing numbers — run the reanalyse command after saving to apply new values to historical data.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
