<?php

namespace App\Filament\Resources\IvrImports\Pages;

use App\Filament\Resources\IvrImports\IvrImportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIvrImport extends EditRecord
{
    protected static string $resource = IvrImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
