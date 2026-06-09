<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Filament\Widgets\WhatsAppCampaignStatsWidget;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppCampaign extends EditRecord
{
    protected static string $resource = WhatsAppCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WhatsAppCampaignStatsWidget::make([
                'campaignId' => $this->getRecord()->getKey(),
            ]),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
