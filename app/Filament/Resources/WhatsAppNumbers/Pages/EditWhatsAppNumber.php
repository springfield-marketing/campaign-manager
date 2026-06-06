<?php

namespace App\Filament\Resources\WhatsAppNumbers\Pages;

use App\Filament\Resources\WhatsAppNumbers\WhatsAppNumberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppNumber extends EditRecord
{
    protected static string $resource = WhatsAppNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
