<?php

namespace App\Filament\Resources\IvrImports\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImportErrorsRelationManager extends RelationManager
{
    protected static string $relationship = 'errors';
    protected static ?string $title = 'Error Report';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')
                    ->label('Row')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('error_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('error_message')
                    ->label('Message')
                    ->wrap()
                    ->limit(120),
            ])
            ->defaultSort('row_number', 'asc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
