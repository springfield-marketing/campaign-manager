<?php

namespace App\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class IvrTrendChartWidget extends ChartWidget
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
        return 'line';
    }

    public function getHeading(): string
    {
        return 'Monthly Performance — ' . $this->year;
    }

    public function getDescription(): string
    {
        return 'Answer rate, lead conversion rate, and unsubscribe rate for every month in the selected year. Always shows the full year regardless of the month filter.';
    }

    protected function getData(): array
    {
        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $answerRates = array_fill(0, 12, null);
        $leadRates   = array_fill(0, 12, null);
        $unsubRates  = array_fill(0, 12, null);

        $rows = DB::table('ivr_monthly_summaries')
            ->where('year', $this->year)
            ->whereNotNull('month')
            ->orderBy('month')
            ->get(['month', 'total_calls', 'answered_calls', 'leads', 'more_info', 'unsubscribed']);

        foreach ($rows as $row) {
            $i        = (int) $row->month - 1;
            $total    = (int) $row->total_calls;
            $answered = (int) $row->answered_calls;
            $leads    = (int) $row->leads + (int) $row->more_info;
            $unsub    = (int) $row->unsubscribed;

            $answerRates[$i] = $total > 0    ? round($answered / $total    * 100, 1) : 0;
            $leadRates[$i]   = $answered > 0 ? round($leads    / $answered * 100, 1) : 0;
            $unsubRates[$i]  = $answered > 0 ? round($unsub    / $answered * 100, 1) : 0;
        }

        return [
            'labels'   => $monthLabels,
            'datasets' => [
                [
                    'label'           => 'Answer Rate %',
                    'data'            => $answerRates,
                    'borderColor'     => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.08)',
                    'tension'         => 0.3,
                    'fill'            => true,
                ],
                [
                    'label'           => 'Lead Conversion %',
                    'data'            => $leadRates,
                    'borderColor'     => 'rgb(99, 102, 241)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.08)',
                    'tension'         => 0.3,
                    'fill'            => true,
                ],
                [
                    'label'           => 'Unsubscribe Rate %',
                    'data'            => $unsubRates,
                    'borderColor'     => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.04)',
                    'tension'         => 0.3,
                    'fill'            => true,
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
                    'ticks'       => [
                        'callback' => RawJs::make("function(v) { return v + '%'; }"),
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => RawJs::make("function(ctx) { return ctx.dataset.label + ': ' + ctx.raw + '%'; }"),
                    ],
                ],
            ],
        ];
    }
}
