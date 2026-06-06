<?php

namespace App\Filament\Resources\IvrCampaigns;

use App\Filament\Resources\IvrCampaigns\Pages\CreateIvrCampaign;
use App\Filament\Resources\IvrCampaigns\Pages\EditIvrCampaign;
use App\Filament\Resources\IvrCampaigns\Pages\ListIvrCampaigns;
use App\Filament\Resources\IvrCampaigns\Schemas\IvrCampaignForm;
use App\Filament\Resources\IvrCampaigns\Tables\IvrCampaignsTable;
use App\Filament\Resources\IvrCampaigns\RelationManagers\CallRecordsRelationManager;
use App\Modules\IVR\Models\IvrCampaign;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IvrCampaignResource extends Resource
{
    protected static ?string $model = IvrCampaign::class;


    public static function form(Schema $schema): Schema
    {
        return IvrCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IvrCampaignsTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-megaphone'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 20; }
    public static function getModelLabel(): string { return 'IVR Campaign'; }
    public static function getPluralModelLabel(): string { return 'IVR Campaigns'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'ivr-campaigns';
    }

    public static function getRelations(): array
    {
        return [
            CallRecordsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIvrCampaigns::route('/'),
            'create' => CreateIvrCampaign::route('/create'),
            'edit' => EditIvrCampaign::route('/{record}/edit'),
        ];
    }
}
