<x-filament-panels::page>
    {{-- Breakdown of FAILED messages by reason — how many messages and how many distinct
         numbers each reason hit, so you can diagnose delivery problems and stop sending to
         numbers that consistently fail. --}}
    {{ $this->table }}
</x-filament-panels::page>
