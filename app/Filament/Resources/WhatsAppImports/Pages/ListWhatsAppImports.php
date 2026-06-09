<?php

namespace App\Filament\Resources\WhatsAppImports\Pages;

use App\Filament\Resources\WhatsAppImports\WhatsAppImportResource;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppImportType;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsAppImports extends ListRecords
{
    protected static string $resource = WhatsAppImportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'campaign_results' => Tab::make('Campaign Results')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', WhatsAppImportType::CampaignResults->value)),
            'unsubscribers' => Tab::make('Unsubscribers')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', WhatsAppImportType::Unsubscribers->value)),
            'pending' => Tab::make('Pending / Processing')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    WhatsAppImportStatus::Pending->value,
                    WhatsAppImportStatus::Processing->value,
                ])),
            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    WhatsAppImportStatus::Failed->value,
                    WhatsAppImportStatus::CompletedWithErrors->value,
                    WhatsAppImportStatus::RevertFailed->value,
                ])),
        ];
    }
}
