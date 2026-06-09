<?php

namespace App\Filament\Resources\IvrUnsubscribers;

use App\Filament\Resources\IvrUnsubscribers\Pages\ListIvrUnsubscribers;
use App\Filament\Resources\IvrUnsubscribers\Tables\IvrUnsubscribersTable;
use App\Models\ContactSuppression;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IvrUnsubscriberResource extends Resource
{
    protected static ?string $model = ContactSuppression::class;

    public static function getNavigationIcon(): string { return 'heroicon-o-no-symbol'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 50; }
    public static function getNavigationLabel(): string { return 'DNC List'; }
    public static function getModelLabel(): string { return 'IVR Do Not Call Number'; }
    public static function getPluralModelLabel(): string { return 'IVR Do Not Call List'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'ivr-unsubscribers';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('channel', 'ivr')
            ->whereIn('reason', ['unsubscribe', 'customer_unsubscribed'])
            ->whereNull('released_at')
            ->with(['phoneNumber.client']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return IvrUnsubscribersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIvrUnsubscribers::route('/'),
        ];
    }
}
