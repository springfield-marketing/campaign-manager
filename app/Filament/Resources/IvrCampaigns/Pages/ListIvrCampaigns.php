<?php

namespace App\Filament\Resources\IvrCampaigns\Pages;

use App\Filament\Resources\IvrCampaigns\IvrCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIvrCampaigns extends ListRecords
{
    protected static string $resource = IvrCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
