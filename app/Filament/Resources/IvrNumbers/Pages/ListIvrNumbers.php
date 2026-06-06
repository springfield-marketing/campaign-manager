<?php

namespace App\Filament\Resources\IvrNumbers\Pages;

use App\Filament\Resources\IvrNumbers\IvrNumberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIvrNumbers extends ListRecords
{
    protected static string $resource = IvrNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
