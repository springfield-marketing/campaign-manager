<x-filament-panels::page>
    {{-- Filter --}}
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    {{-- Stats --}}
    @livewire(\App\Filament\Widgets\IvrStatsWidget::class, ['year' => $this->year, 'month' => $this->month])

    {{-- Monthly budget (current month only) --}}
    @if ($this->isCurrentMonth())
        @livewire(\App\Filament\Widgets\IvrBudgetWidget::class, ['year' => $this->year, 'month' => $this->month])
    @endif

    {{-- Insights: monthly trend --}}
    @livewire(\App\Filament\Widgets\IvrTrendChartWidget::class, ['year' => $this->year, 'month' => $this->month])

    {{-- Insights: efficiency stats (minutes/lead, best/worst campaign) --}}
    @livewire(\App\Filament\Widgets\IvrEfficiencyStatsWidget::class, ['year' => $this->year, 'month' => $this->month])

    {{-- Insights: call timing (hour + day of week, side by side) --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @livewire(\App\Filament\Widgets\IvrHourlyChartWidget::class, ['year' => $this->year, 'month' => $this->month])
        @livewire(\App\Filament\Widgets\IvrDayOfWeekChartWidget::class, ['year' => $this->year, 'month' => $this->month])
    </div>

    {{-- Campaign breakdown table --}}
    {{ $this->table }}
</x-filament-panels::page>
