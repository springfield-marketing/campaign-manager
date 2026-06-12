<?php

namespace App\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class IvrHourlyChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;
    protected static bool $isLazy = false;

    public int $year;
    public ?int $month = null;

    public function mount(int $year = 0, ?int $month = null): void
    {
        $this->year  = $year ?: now()->year;
        $this->month = $month;
        parent::mount();
    }

    #[On('ivr-filter-changed')]
    public function onFilterChanged(int $year, ?int $month): void
    {
        $this->year  = $year;
        $this->month = $month;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): string
    {
        return 'Answer Rate by Hour of Day';
    }

    public function getDescription(): string
    {
        return 'Which call times get the most pickups. Based on all calls in the selected period.';
    }

    protected function getData(): array
    {
        $driver = DB::connection()->getDriverName();

        $hourExpr = $driver === 'sqlite'
            ? "CAST(strftime('%H', call_time) AS integer)"
            : 'EXTRACT(HOUR FROM call_time)::int';

        $rows = DB::table('ivr_call_records')
            ->selectRaw("{$hourExpr} AS hour")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN call_status = 'Answered' THEN 1 ELSE 0 END) AS answered")
            ->whereYear('call_time', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('call_time', $this->month))
            ->groupByRaw($hourExpr)
            ->orderByRaw($hourExpr)
            ->get()
            ->keyBy(fn ($r) => (int) $r->hour);

        $labels = [];
        $rates  = [];

        for ($h = 0; $h <= 23; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $row      = $rows->get($h);
            $total    = $row ? (int) $row->total    : 0;
            $answered = $row ? (int) $row->answered : 0;
            $rates[]  = $total > 0 ? round($answered / $total * 100, 1) : 0;
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => 'Answer Rate %',
                    'data'            => $rates,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.7)',
                    'borderRadius'    => 4,
                ],
            ],
        ];
    }

    protected function getOptions(): array | RawJs | null
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max'         => 100,
                    'ticks'       => [
                        'callback' => RawJs::make("function(v) { return v + '%'; }"),
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => RawJs::make("function(ctx) { return 'Answer rate: ' + ctx.raw + '%'; }"),
                    ],
                ],
            ],
        ];
    }
}
