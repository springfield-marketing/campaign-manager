<?php

namespace App\Filament\Resources\MarketingAreas\Pages;

use App\Filament\Resources\MarketingAreas\MarketingAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingAreas extends ListRecords
{
    protected static string $resource = MarketingAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
