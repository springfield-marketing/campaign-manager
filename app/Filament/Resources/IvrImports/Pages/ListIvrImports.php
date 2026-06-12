<?php

namespace App\Filament\Resources\IvrImports\Pages;

use App\Filament\Resources\IvrImports\IvrImportResource;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListIvrImports extends ListRecords
{
    protected static string $resource = IvrImportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'campaign_results' => Tab::make('Campaign Results')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', IvrImportType::CampaignResults->value)),
            'unsubscribers' => Tab::make('Do Not Call List')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', IvrImportType::Unsubscribers->value)),
            'pending' => Tab::make('Pending / Processing')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    IvrImportStatus::Pending->value,
                    IvrImportStatus::Processing->value,
                ])),
            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    IvrImportStatus::Failed->value,
                    IvrImportStatus::CompletedWithErrors->value,
                    IvrImportStatus::RevertFailed->value,
                ])),
        ];
    }
}
