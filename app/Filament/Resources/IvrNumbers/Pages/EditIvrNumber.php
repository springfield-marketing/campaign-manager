<?php

namespace App\Filament\Resources\IvrNumbers\Pages;

use App\Filament\Resources\IvrNumbers\IvrNumberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIvrNumber extends EditRecord
{
    protected static string $resource = IvrNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
