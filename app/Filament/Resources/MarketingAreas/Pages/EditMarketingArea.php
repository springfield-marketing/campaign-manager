<?php

namespace App\Filament\Resources\MarketingAreas\Pages;

use App\Filament\Resources\MarketingAreas\MarketingAreaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketingArea extends EditRecord
{
    protected static string $resource = MarketingAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
