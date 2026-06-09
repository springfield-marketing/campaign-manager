<?php

namespace App\Filament\Resources\WhatsAppImports;

use App\Filament\Resources\WhatsAppImports\Pages\CreateWhatsAppImport;
use App\Filament\Resources\WhatsAppImports\Pages\EditWhatsAppImport;
use App\Filament\Resources\WhatsAppImports\Pages\ListWhatsAppImports;
use App\Filament\Resources\WhatsAppImports\RelationManagers\ImportErrorsRelationManager;
use App\Filament\Resources\WhatsAppImports\Schemas\WhatsAppImportForm;
use App\Filament\Resources\WhatsAppImports\Tables\WhatsAppImportsTable;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppImportResource extends Resource
{
    protected static ?string $model = WhatsAppImport::class;


    public static function form(Schema $schema): Schema
    {
        return WhatsAppImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppImportsTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-inbox-arrow-down'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 10; }
    public static function getNavigationLabel(): string { return 'Imports'; }
    public static function getModelLabel(): string { return 'WhatsApp Import'; }
    public static function getPluralModelLabel(): string { return 'WhatsApp Imports'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-imports'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type', ['campaign_results', 'unsubscribers']);
    }


    public static function getRelations(): array
    {
        return [
            ImportErrorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppImports::route('/'),
            'create' => CreateWhatsAppImport::route('/create'),
            'edit' => EditWhatsAppImport::route('/{record}/edit'),
        ];
    }
}
