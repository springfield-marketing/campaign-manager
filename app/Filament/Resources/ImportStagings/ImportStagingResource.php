<?php

namespace App\Filament\Resources\ImportStagings;

use App\Filament\Resources\ImportStagings\Pages\CreateImportStaging;
use App\Filament\Resources\ImportStagings\Pages\EditImportStaging;
use App\Filament\Resources\ImportStagings\Pages\ListImportStagings;
use App\Filament\Resources\ImportStagings\Schemas\ImportStagingForm;
use App\Filament\Resources\ImportStagings\Tables\ImportStagingsTable;
use App\Models\ImportStaging;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ImportStagingResource extends Resource
{
    protected static ?string $model = ImportStaging::class;


    public static function form(Schema $schema): Schema
    {
        return ImportStagingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportStagingsTable::configure($table);
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-inbox-arrow-down';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Imports';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getModelLabel(): string
    {
        return 'Staged Row';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Import Staging';
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportStagings::route('/'),
            'create' => CreateImportStaging::route('/create'),
            'edit' => EditImportStaging::route('/{record}/edit'),
        ];
    }
}
