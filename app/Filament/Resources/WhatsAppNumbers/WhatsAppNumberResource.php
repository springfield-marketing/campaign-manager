<?php

namespace App\Filament\Resources\WhatsAppNumbers;

use App\Filament\Resources\WhatsAppNumbers\Pages\CreateWhatsAppNumber;
use App\Filament\Resources\WhatsAppNumbers\Pages\EditWhatsAppNumber;
use App\Filament\Resources\WhatsAppNumbers\Pages\ListWhatsAppNumbers;
use App\Filament\Resources\WhatsAppNumbers\Schemas\WhatsAppNumberForm;
use App\Filament\Resources\WhatsAppNumbers\Tables\WhatsAppNumbersTable;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsAppNumberResource extends Resource
{
    protected static ?string $model = ClientPhoneNumber::class;


    public static function form(Schema $schema): Schema
    {
        return WhatsAppNumberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppNumbersTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-chat-bubble-left-right'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 30; }
    public static function getModelLabel(): string { return 'WhatsApp Number'; }
    public static function getPluralModelLabel(): string { return 'WhatsApp Numbers'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-numbers'; }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('whatsAppMessages')
            ->with(['whatsAppProfile', 'client.primaryEmail']);
    }


    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppNumbers::route('/'),
            'create' => CreateWhatsAppNumber::route('/create'),
            'edit' => EditWhatsAppNumber::route('/{record}/edit'),
        ];
    }
}
