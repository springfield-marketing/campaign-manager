<?php

namespace App\Filament\Resources\IvrImports\Pages;

use App\Filament\Resources\IvrImports\IvrImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIvrImports extends ListRecords
{
    protected static string $resource = IvrImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
