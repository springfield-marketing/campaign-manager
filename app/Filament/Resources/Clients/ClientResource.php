<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\Clients\Tables\ClientsTable;
use App\Models\Client;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;


    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }


    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Contacts';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getModelLabel(): string
    {
        return 'Contact';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Contacts';
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OwnershipsRelationManager::class,
            RelationManagers\PhoneNumbersRelationManager::class,
            RelationManagers\EmailsRelationManager::class,
            RelationManagers\SourcesRelationManager::class,
            RelationManagers\ActivityTimelineRelationManager::class,
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
