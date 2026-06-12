<?php

namespace App\Filament\Resources\IvrCampaigns\Pages;

use App\Filament\Resources\IvrCampaigns\IvrCampaignResource;
use App\Filament\Widgets\IvrCampaignStatsWidget;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIvrCampaign extends EditRecord
{
    protected static string $resource = IvrCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IvrCampaignStatsWidget::make([
                'campaignId' => $this->getRecord()->getKey(),
            ]),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
