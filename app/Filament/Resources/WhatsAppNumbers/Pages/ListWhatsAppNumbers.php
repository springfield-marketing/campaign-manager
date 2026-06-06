<?php

namespace App\Filament\Resources\WhatsAppNumbers\Pages;

use App\Filament\Resources\WhatsAppNumbers\WhatsAppNumberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppNumbers extends ListRecords
{
    protected static string $resource = WhatsAppNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
