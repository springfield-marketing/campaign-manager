<?php

namespace App\Filament\Resources\OfficialAreas\Pages;

use App\Filament\Resources\OfficialAreas\OfficialAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOfficialAreas extends ListRecords
{
    protected static string $resource = OfficialAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
