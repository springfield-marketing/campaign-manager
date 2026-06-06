<?php

namespace App\Filament\Resources\WhatsAppImports\Pages;

use App\Filament\Resources\WhatsAppImports\WhatsAppImportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppImport extends EditRecord
{
    protected static string $resource = WhatsAppImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
