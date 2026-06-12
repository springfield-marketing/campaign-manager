<?php

namespace App\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class IvrDayOfWeekChartWidget extends ChartWidget
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
        return 'Answer Rate by Day of Week';
    }

    public function getDescription(): string
    {
        return 'Which days get the most pickups. Red bars = UAE weekend (Fri–Sat).';
    }

    protected function getData(): array
    {
        $driver = DB::connection()->getDriverName();

        // Both PostgreSQL DOW and SQLite %w use 0=Sunday … 6=Saturday
        $dowExpr = $driver === 'sqlite'
            ? "CAST(strftime('%w', call_time) AS integer)"
            : 'EXTRACT(DOW FROM call_time)::int';

        $rows = DB::table('ivr_call_records')
            ->selectRaw("{$dowExpr} AS dow")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN call_status = 'Answered' THEN 1 ELSE 0 END) AS answered")
            ->whereYear('call_time', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('call_time', $this->month))
            ->groupByRaw($dowExpr)
            ->orderByRaw($dowExpr)
            ->get()
            ->keyBy(fn ($r) => (int) $r->dow);

        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $rates  = [];
        $colors = [];

        foreach (range(0, 6) as $d) {
            $row      = $rows->get($d);
            $total    = $row ? (int) $row->total    : 0;
            $answered = $row ? (int) $row->answered : 0;
            $rates[]  = $total > 0 ? round($answered / $total * 100, 1) : 0;
            // Fri (5) and Sat (6) are UAE weekend — highlight in red
            $colors[] = in_array($d, [5, 6], true)
                ? 'rgba(239, 68, 68, 0.55)'
                : 'rgba(99, 102, 241, 0.7)';
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => 'Answer Rate %',
                    'data'            => $rates,
                    'backgroundColor' => $colors,
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
