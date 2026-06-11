<x-filament-panels::page>
    {{-- Filter --}}
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    {{-- Period summary stats --}}
    @livewire(\App\Filament\Widgets\WhatsAppReportsStatsWidget::class, ['year' => $this->year, 'month' => $this->month])

    {{-- Campaign breakdown table --}}
    {{ $this->table }}
</x-filament-panels::page>
