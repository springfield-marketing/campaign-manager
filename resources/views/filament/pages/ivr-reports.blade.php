<x-filament-panels::page>
    {{-- Filter bar --}}
    <div class="flex flex-wrap items-end gap-3">
        {{ $this->form }}
        <x-filament::button wire:click="apply" color="primary" class="mb-[1px]">Apply</x-filament::button>
    </div>

    {{-- Stats cards (rendered as a proper StatsOverviewWidget) --}}
    {{ $this->headerWidgets }}

    {{-- Monthly budget (only shown for current month) --}}
    @php $budget = $this->getMonthlyBudget(); @endphp
    @if ($budget !== null)
        <x-filament::section heading="Monthly Budget">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Quota</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($budget['minutes_quota']) }} min</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Remaining</p>
                    <p class="mt-1 text-2xl font-bold {{ $budget['minutes_remaining'] <= 0 ? 'text-red-500' : 'text-gray-900 dark:text-white' }}">
                        {{ number_format($budget['minutes_remaining']) }} min
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Budget / day <span class="font-normal">({{ $budget['remaining_working_days'] }} working days left)</span>
                    </p>
                    <p class="mt-1 text-2xl font-bold {{ $budget['minutes_remaining'] <= 0 ? 'text-red-500' : 'text-gray-900 dark:text-white' }}">
                        {{ number_format($budget['minutes_per_day']) }} min/day
                    </p>
                </div>
            </div>
            @if ($budget['minutes_used'] > $budget['minutes_quota'])
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-400">
                    Quota exceeded by {{ number_format($budget['minutes_used'] - $budget['minutes_quota']) }} minutes — over-quota rate applies.
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- Campaign breakdown as a proper Filament table --}}
    {{ $this->table }}
</x-filament-panels::page>
