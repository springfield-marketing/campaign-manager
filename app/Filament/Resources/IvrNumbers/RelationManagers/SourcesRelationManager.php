<?php

namespace App\Filament\Resources\IvrNumbers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'sources';
    protected static ?string $title = 'Import Sources';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_name')
                    ->label('Source Name')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'ivr'       => 'primary',
                        'whatsapp'  => 'success',
                        default     => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('source_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                    ->placeholder('—'),

                TextColumn::make('source_file_name')
                    ->label('File')
                    ->limit(30)
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Imported At')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
