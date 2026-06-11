<?php

namespace App\Filament\Resources\WhatsAppNumbers;

use App\Filament\Resources\WhatsAppNumbers\Pages\CreateWhatsAppNumber;
use App\Filament\Resources\WhatsAppNumbers\Pages\EditWhatsAppNumber;
use App\Filament\Resources\WhatsAppNumbers\Pages\ListWhatsAppNumbers;
use App\Filament\Resources\WhatsAppNumbers\RelationManagers\SuppressionsRelationManager;
use App\Filament\Resources\WhatsAppNumbers\RelationManagers\WhatsAppMessagesRelationManager;
use App\Filament\Resources\WhatsAppNumbers\Schemas\WhatsAppNumberForm;
use App\Filament\Resources\WhatsAppNumbers\Tables\WhatsAppNumbersTable;
use App\Models\ClientPhoneNumber;
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
    public static function getNavigationLabel(): string { return 'Numbers'; }
    public static function getModelLabel(): string { return 'WhatsApp Number'; }
    public static function getPluralModelLabel(): string { return 'WhatsApp Numbers'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-numbers'; }

    public static function getRelations(): array
    {
        return [
            WhatsAppMessagesRelationManager::class,
            SuppressionsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query): void {
                // UAE: only mobile numbers (national_number starts with 5 — landlines start with 2,4,6,7 etc)
                $query->where(function (Builder $q): void {
                    $q->where('is_uae', true)
                      ->where('national_number', 'like', '5%');
                // Non-UAE: include all — can't classify mobile vs landline without per-country data
                })->orWhere('is_uae', false);
            })
            ->withExists(['suppressions as is_whatsapp_suppressed' => fn ($q) =>
                $q->where('channel', 'whatsapp')->whereNull('released_at')
            ])
            ->with(['whatsAppProfile', 'client.primaryEmail', 'client.tags']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListWhatsAppNumbers::route('/'),
            'create' => CreateWhatsAppNumber::route('/create'),
            'edit'   => EditWhatsAppNumber::route('/{record}/edit'),
        ];
    }
}
