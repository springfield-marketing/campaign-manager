<?php

namespace App\Filament\Resources\IvrScripts;

use App\Filament\Resources\IvrScripts\Pages\CreateIvrScript;
use App\Filament\Resources\IvrScripts\Pages\EditIvrScript;
use App\Filament\Resources\IvrScripts\Pages\ListIvrScripts;
use App\Filament\Resources\IvrScripts\Schemas\IvrScriptForm;
use App\Filament\Resources\IvrScripts\Tables\IvrScriptsTable;
use App\Modules\IVR\Models\IvrScript;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IvrScriptResource extends Resource
{
    protected static ?string $model = IvrScript::class;


    public static function form(Schema $schema): Schema
    {
        return IvrScriptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IvrScriptsTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-musical-note'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 30; }
    public static function getModelLabel(): string { return 'IVR Script'; }
    public static function getPluralModelLabel(): string { return 'IVR Scripts'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'ivr-scripts';
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
            'index' => ListIvrScripts::route('/'),
            'create' => CreateIvrScript::route('/create'),
            'edit' => EditIvrScript::route('/{record}/edit'),
        ];
    }
}
