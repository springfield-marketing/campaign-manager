<?php

namespace App\Filament\Resources\WhatsAppCampaigns;

use App\Filament\Resources\WhatsAppCampaigns\Pages\CreateWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\EditWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\ListWhatsAppCampaigns;
use App\Filament\Resources\WhatsAppCampaigns\Schemas\WhatsAppCampaignForm;
use App\Filament\Resources\WhatsAppCampaigns\Tables\WhatsAppCampaignsTable;
use App\Modules\WhatsApp\Models\WhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\RelationManagers\MessagesRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsAppCampaignResource extends Resource
{
    use \App\Filament\Concerns\RestrictsToWhatsApp;

    protected static ?string $model = WhatsAppCampaign::class;


    public static function form(Schema $schema): Schema
    {
        return WhatsAppCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppCampaignsTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-megaphone'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 20; }
    public static function getNavigationLabel(): string { return 'Campaigns'; }
    public static function getModelLabel(): string { return 'WhatsApp Campaign'; }
    public static function getPluralModelLabel(): string { return 'WhatsApp Campaigns'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-campaigns'; }
    public static function getRelations(): array { return [MessagesRelationManager::class]; }


    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppCampaigns::route('/'),
            'create' => CreateWhatsAppCampaign::route('/create'),
            'edit' => EditWhatsAppCampaign::route('/{record}/edit'),
        ];
    }
}
