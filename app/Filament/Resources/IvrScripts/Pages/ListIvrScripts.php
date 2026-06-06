<?php

namespace App\Filament\Resources\IvrScripts\Pages;

use App\Filament\Resources\IvrScripts\IvrScriptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIvrScripts extends ListRecords
{
    protected static string $resource = IvrScriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
