<?php

namespace App\Filament\Widgets;

use App\Modules\WhatsApp\Models\WhatsAppCampaign;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class WhatsAppReportsStatsWidget extends StatsOverviewWidget
{
    public int $year;
    public ?int $month;

    protected static bool $isLazy = false;
    protected static bool $isDiscovered = false;

    public function mount(int $year = 0, ?int $month = null): void
    {
        $this->year  = $year ?: now()->year;
        $this->month = $month ?? now()->month;
    }

    #[On('whatsapp-reports-filter-changed')]
    public function onFilterChanged(int $year, ?int $month): void
    {
        $this->year  = $year;
        $this->month = $month;
    }

    protected function getStats(): array
    {
        $totals = WhatsAppCampaign::query()
            ->when($this->year,  fn ($q) => $q->whereYear('started_at', $this->year))
            ->when($this->month, fn ($q) => $q->whereMonth('started_at', $this->month))
            ->selectRaw('
                COUNT(*) AS campaigns,
                COALESCE(SUM(total_messages), 0)     AS total,
                COALESCE(SUM(delivered_count), 0)    AS delivered,
                COALESCE(SUM(read_count), 0)         AS read_count,
                COALESCE(SUM(replied_count), 0)      AS replied,
                COALESCE(SUM(failed_count), 0)       AS failed,
                COALESCE(SUM(unsubscribed_count), 0) AS unsubscribed
            ')
            ->first();

        $campaigns  = (int) $totals->campaigns;
        $total      = (int) $totals->total;
        $delivered  = (int) $totals->delivered;
        $read       = (int) $totals->read_count;
        $replied    = (int) $totals->replied;
        $failed     = (int) $totals->failed;
        $unsubscribed = (int) $totals->unsubscribed;

        $deliveryRate = $total > 0
            ? number_format($delivered / $total * 100, 1) . '% delivery rate'
            : null;

        $readRate = $delivered > 0
            ? number_format($read / $delivered * 100, 1) . '% of delivered'
            : null;

        $replyRate = $total > 0
            ? number_format($replied / $total * 100, 1) . '% reply rate'
            : null;

        $failRate = $total > 0
            ? number_format($failed / $total * 100, 1) . '% failure rate'
            : null;

        return [
            Stat::make('Total Sent', number_format($total))
                ->icon('heroicon-o-paper-airplane')
                ->description($campaigns > 0 ? "{$campaigns} campaign".($campaigns !== 1 ? 's' : '') : null),

            Stat::make('Delivered', number_format($delivered))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($deliveryRate),

            Stat::make('Read', number_format($read))
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->description($readRate),

            Stat::make('Replied', number_format($replied))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->description($replyRate),

            Stat::make('Failed', number_format($failed))
                ->icon('heroicon-o-x-circle')
                ->color($failed > 0 ? 'danger' : 'gray')
                ->description($failRate),

            Stat::make('Unsubscribed', number_format($unsubscribed))
                ->icon('heroicon-o-no-symbol')
                ->color($unsubscribed > 0 ? 'warning' : 'gray'),
        ];
    }
}
