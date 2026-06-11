<?php

namespace App\Filament\Resources\WhatsAppUnsubscribers;

use App\Filament\Resources\WhatsAppUnsubscribers\Pages\ListWhatsAppUnsubscribers;
use App\Filament\Resources\WhatsAppUnsubscribers\Tables\WhatsAppUnsubscribersTable;
use App\Models\ContactSuppression;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppUnsubscriberResource extends Resource
{
    protected static ?string $model = ContactSuppression::class;

    public static function getNavigationIcon(): string { return 'heroicon-o-no-symbol'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 50; }
    public static function getNavigationLabel(): string { return 'DNC List'; }
    public static function getModelLabel(): string { return 'WhatsApp Do Not Message Number'; }
    public static function getPluralModelLabel(): string { return 'WhatsApp Do Not Message List'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'whatsapp-unsubscribers';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('channel', 'whatsapp')
            ->whereNull('released_at')
            ->with(['phoneNumber.client']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppUnsubscribersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppUnsubscribers::route('/'),
        ];
    }
}
