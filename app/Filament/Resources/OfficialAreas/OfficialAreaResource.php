<?php

namespace App\Filament\Resources\OfficialAreas;

use App\Filament\Resources\OfficialAreas\Pages\CreateOfficialArea;
use App\Filament\Resources\OfficialAreas\Pages\EditOfficialArea;
use App\Filament\Resources\OfficialAreas\Pages\ListOfficialAreas;
use App\Filament\Resources\OfficialAreas\Schemas\OfficialAreaForm;
use App\Filament\Resources\OfficialAreas\Tables\OfficialAreasTable;
use App\Models\OfficialArea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OfficialAreaResource extends Resource
{
    protected static ?string $model = OfficialArea::class;

    public static function form(Schema $schema): Schema
    {
        return OfficialAreaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfficialAreasTable::configure($table);
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-map-pin';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Geography';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getModelLabel(): string
    {
        return 'Official Area';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Official Areas';
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
            'index' => ListOfficialAreas::route('/'),
            'create' => CreateOfficialArea::route('/create'),
            'edit' => EditOfficialArea::route('/{record}/edit'),
        ];
    }
}
