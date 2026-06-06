<?php

namespace App\Filament\Resources\PlaceAliases;

use App\Filament\Resources\PlaceAliases\Pages\CreatePlaceAlias;
use App\Filament\Resources\PlaceAliases\Pages\EditPlaceAlias;
use App\Filament\Resources\PlaceAliases\Pages\ListPlaceAliases;
use App\Filament\Resources\PlaceAliases\Schemas\PlaceAliasForm;
use App\Filament\Resources\PlaceAliases\Tables\PlaceAliasesTable;
use App\Models\PlaceAlias;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PlaceAliasResource extends Resource
{
    protected static ?string $model = PlaceAlias::class;

    public static function form(Schema $schema): Schema
    {
        return PlaceAliasForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlaceAliasesTable::configure($table);
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Geography';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getModelLabel(): string
    {
        return 'Place Alias';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Place Aliases';
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
            'index' => ListPlaceAliases::route('/'),
            'create' => CreatePlaceAlias::route('/create'),
            'edit' => EditPlaceAlias::route('/{record}/edit'),
        ];
    }
}
