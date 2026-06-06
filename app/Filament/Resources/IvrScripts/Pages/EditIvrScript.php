<?php

namespace App\Filament\Resources\IvrScripts\Pages;

use App\Filament\Resources\IvrScripts\IvrScriptResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIvrScript extends EditRecord
{
    protected static string $resource = IvrScriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
