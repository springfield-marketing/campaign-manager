<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\ImportStaging;
use App\Models\Ownership;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingReviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stagingQueued  = ImportStaging::where('status', 'pending')->count();
        $totalContacts  = Client::count();
        $totalOwnership = Ownership::count();

        return [
            Stat::make('Staging Queue', $stagingQueued)
                ->description('Rows waiting to be processed')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color($stagingQueued > 0 ? 'info' : 'gray'),

            Stat::make('Total Contacts', $totalContacts)
                ->description('Contacts in database')
                ->icon('heroicon-o-users')
                ->color('primary')
                ->url(route('filament.admin.resources.clients.index')),

            Stat::make('Total Properties', $totalOwnership)
                ->description('Ownership records')
                ->icon('heroicon-o-building-office-2')
                ->color('primary'),
        ];
    }
}
