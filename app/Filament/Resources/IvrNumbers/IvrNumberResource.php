<?php

namespace App\Filament\Resources\IvrNumbers;

use App\Filament\Resources\IvrNumbers\Pages\CreateIvrNumber;
use App\Filament\Resources\IvrNumbers\Pages\EditIvrNumber;
use App\Filament\Resources\IvrNumbers\Pages\ListIvrNumbers;
use App\Filament\Resources\IvrNumbers\RelationManagers\IvrCallRecordsRelationManager;
use App\Filament\Resources\IvrNumbers\RelationManagers\SourcesRelationManager;
use App\Filament\Resources\IvrNumbers\RelationManagers\SuppressionsRelationManager;
use App\Filament\Resources\IvrNumbers\Schemas\IvrNumberForm;
use App\Filament\Resources\IvrNumbers\Tables\IvrNumbersTable;
use App\Models\ClientPhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IvrNumberResource extends Resource
{
    protected static ?string $model = ClientPhoneNumber::class;
    protected static ?string $recordTitleAttribute = 'normalized_phone';


    public static function form(Schema $schema): Schema
    {
        return IvrNumberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IvrNumbersTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-phone'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 40; }
    public static function getNavigationLabel(): string { return 'Numbers'; }
    public static function getModelLabel(): string { return 'IVR Number'; }
    public static function getPluralModelLabel(): string { return 'IVR Numbers'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'ivr-numbers';
    }

    public static function getRelations(): array
    {
        return [
            IvrCallRecordsRelationManager::class,
            SourcesRelationManager::class,
            SuppressionsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_uae', true)
            ->where('normalized_phone', 'like', '+9715%')
            ->whereRaw('LENGTH(normalized_phone) = 13')
            ->with(['ivrProfile', 'client.primaryEmail'])
            ->withExists(['suppressions as is_ivr_suppressed' => fn (Builder $q) => $q->activeIvr()]);
    }


    public static function getPages(): array
    {
        return [
            'index' => ListIvrNumbers::route('/'),
            'create' => CreateIvrNumber::route('/create'),
            'edit' => EditIvrNumber::route('/{record}/edit'),
        ];
    }
}
