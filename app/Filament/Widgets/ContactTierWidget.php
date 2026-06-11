<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ContactTierWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;
    protected static ?int $sort = 11;

    protected function getStats(): array
    {
        $total = (int) DB::table('clients')->count();

        if ($total === 0) {
            return [];
        }

        $tiers = DB::table('clients')
            ->selectRaw("tier, count(*) as cnt")
            ->groupBy('tier')
            ->pluck('cnt', 'tier');

        $vip   = (int) ($tiers['vip']            ?? 0);
        $hnw   = (int) ($tiers['high_net_worth']  ?? 0);
        $prem  = (int) ($tiers['premium']         ?? 0);
        $std   = (int) ($tiers['standard']        ?? 0);
        $unset = (int) ($tiers[null]              ?? $tiers[''] ?? ($total - $vip - $hnw - $prem - $std));

        $scored = (int) DB::table('clients')->whereNotNull('wealth_score')->count();

        return [
            Stat::make('VIP', number_format($vip))
                ->description('Wealth score 75–100')
                ->color('warning')
                ->icon('heroicon-o-star'),

            Stat::make('High Net Worth', number_format($hnw))
                ->description('Wealth score 50–74')
                ->color('success')
                ->icon('heroicon-o-sparkles'),

            Stat::make('Premium', number_format($prem))
                ->description('Wealth score 25–49')
                ->color('info')
                ->icon('heroicon-o-arrow-trending-up'),

            Stat::make('Standard', number_format($std))
                ->description('Wealth score 0–24')
                ->color('gray')
                ->icon('heroicon-o-user-group'),

            Stat::make('Unclassified', number_format($unset))
                ->description($scored > 0 ? number_format($scored) . ' have a wealth score' : 'Run rescore to classify')
                ->color($unset > $total * 0.5 ? 'warning' : 'gray')
                ->icon('heroicon-o-question-mark-circle'),
        ];
    }
}
