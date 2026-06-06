<?php

namespace App\Filament\Resources\WhatsAppImports\Pages;

use App\Filament\Resources\WhatsAppImports\WhatsAppImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppImports extends ListRecords
{
    protected static string $resource = WhatsAppImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
