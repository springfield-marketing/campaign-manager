<?php

namespace App\Filament\Resources\OfficialAreas\Pages;

use App\Filament\Resources\OfficialAreas\OfficialAreaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOfficialArea extends EditRecord
{
    protected static string $resource = OfficialAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
