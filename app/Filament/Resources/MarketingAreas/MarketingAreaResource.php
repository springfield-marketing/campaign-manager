<?php

namespace App\Filament\Resources\MarketingAreas;

use App\Filament\Resources\MarketingAreas\Pages\CreateMarketingArea;
use App\Filament\Resources\MarketingAreas\Pages\EditMarketingArea;
use App\Filament\Resources\MarketingAreas\Pages\ListMarketingAreas;
use App\Filament\Resources\MarketingAreas\Schemas\MarketingAreaForm;
use App\Filament\Resources\MarketingAreas\Tables\MarketingAreasTable;
use App\Filament\Resources\MarketingAreas\RelationManagers\OfficialAreasRelationManager;
use App\Models\MarketingArea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MarketingAreaResource extends Resource
{
    protected static ?string $model = MarketingArea::class;

    public static function form(Schema $schema): Schema
    {
        return MarketingAreaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketingAreasTable::configure($table);
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-globe-alt';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Geography';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getModelLabel(): string
    {
        return 'Marketing Area';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Marketing Areas';
    }


    public static function getRelations(): array
    {
        return [
            OfficialAreasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingAreas::route('/'),
            'create' => CreateMarketingArea::route('/create'),
            'edit' => EditMarketingArea::route('/{record}/edit'),
        ];
    }
}
