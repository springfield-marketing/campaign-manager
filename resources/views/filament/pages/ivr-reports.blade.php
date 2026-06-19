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
</x-filament-panels::page>
