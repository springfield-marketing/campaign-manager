<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DataQualityWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $total = (int) DB::table('clients')->count();

        if ($total === 0) {
            return [Stat::make('Contacts', 0)];
        }

        $noName = (int) DB::table('clients')
            ->where(fn ($q) => $q->whereNull('full_name')->orWhereRaw("trim(full_name) = ''"))
            ->count();

        $noPhone = $total - (int) DB::table('client_phone_numbers')
            ->distinct('client_id')
            ->count('client_id');

        $noEmail = $total - (int) DB::table('client_emails')
            ->distinct('client_id')
            ->count('client_id');

        $noEmirate = (int) DB::table('clients')
            ->whereNull('emirate')
            ->count();

        $noOwnership = $total - (int) DB::table('ownerships')
            ->distinct('client_id')
            ->count('client_id');

        $multiSource = (int) DB::table(
            DB::table('client_sources')
                ->select('client_id')
                ->groupBy('client_id')
                ->havingRaw('count(*) >= 2'),
            'sub'
        )->count();

        $avgCompleteness = (int) DB::table('clients')
            ->whereNotNull('completeness_score')
            ->avg('completeness_score');

        $scored = (int) DB::table('clients')->whereNotNull('completeness_score')->count();
        $unscoredPct = $total > 0 ? round(($total - $scored) / $total * 100) : 0;

        return [
            Stat::make('No Name', number_format($noName))
                ->description(round($noName / $total * 100, 1) . '% of contacts')
                ->color($noName / $total > 0.3 ? 'danger' : 'warning')
                ->icon('heroicon-o-user'),

            Stat::make('No Phone', number_format($noPhone))
                ->description(round($noPhone / $total * 100, 1) . '% of contacts')
                ->color($noPhone / $total > 0.3 ? 'danger' : 'warning')
                ->icon('heroicon-o-phone'),

            Stat::make('No Email', number_format($noEmail))
                ->description(round($noEmail / $total * 100, 1) . '% of contacts')
                ->color('gray')
                ->icon('heroicon-o-envelope'),

            Stat::make('No Emirate', number_format($noEmirate))
                ->description(round($noEmirate / $total * 100, 1) . '% of contacts')
                ->color('gray')
                ->icon('heroicon-o-map-pin'),

            Stat::make('No Property', number_format($noOwnership))
                ->description(round($noOwnership / $total * 100, 1) . '% of contacts')
                ->color('gray')
                ->icon('heroicon-o-building-office'),

            Stat::make('Avg Completeness', $scored > 0 ? $avgCompleteness . '%' : '—')
                ->description($unscoredPct > 0 ? "{$unscoredPct}% not yet scored" : 'All contacts scored')
                ->color($avgCompleteness >= 75 ? 'success' : ($avgCompleteness >= 50 ? 'warning' : 'danger'))
                ->icon('heroicon-o-chart-bar'),

            Stat::make('Multi-source Contacts', number_format($multiSource))
                ->description('Matched across 2+ import sources')
                ->color('success')
                ->icon('heroicon-o-link'),
        ];
    }
}
