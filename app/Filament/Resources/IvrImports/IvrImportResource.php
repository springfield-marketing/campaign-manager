<?php

namespace App\Filament\Resources\IvrImports;

use App\Filament\Resources\IvrImports\Pages\CreateIvrImport;
use App\Filament\Resources\IvrImports\Pages\EditIvrImport;
use App\Filament\Resources\IvrImports\Pages\ListIvrImports;
use App\Filament\Resources\IvrImports\RelationManagers\ImportErrorsRelationManager;
use App\Filament\Resources\IvrImports\Schemas\IvrImportForm;
use App\Filament\Resources\IvrImports\Tables\IvrImportsTable;
use App\Modules\IVR\Models\IvrImport;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IvrImportResource extends Resource
{
    protected static ?string $model = IvrImport::class;


    public static function form(Schema $schema): Schema
    {
        return IvrImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IvrImportsTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-inbox-arrow-down'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 10; }
    public static function getModelLabel(): string { return 'IVR Import'; }
    public static function getPluralModelLabel(): string { return 'IVR Imports'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'ivr-imports';
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
            'index' => ListIvrImports::route('/'),
            'create' => CreateIvrImport::route('/create'),
            'edit' => EditIvrImport::route('/{record}/edit'),
        ];
    }
}
