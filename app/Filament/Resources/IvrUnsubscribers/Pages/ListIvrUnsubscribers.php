<?php

namespace App\Filament\Resources\IvrUnsubscribers\Pages;

use App\Filament\Resources\IvrUnsubscribers\IvrUnsubscriberResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListIvrUnsubscribers extends ListRecords
{
    protected static string $resource = IvrUnsubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Export the current Do-Not-Call list to hand to the dialer/vendor so suppression
            // is enforced platform-side too.
            Action::make('export')
                ->label('Export DNC list (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('ivr.dnc-list.export'))
                ->openUrlInNewTab(),
        ];
    }
}
