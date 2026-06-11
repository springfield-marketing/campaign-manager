<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'sources';
    protected static ?string $title = 'Import Sources';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'ivr'      => 'primary',
                        'whatsapp' => 'success',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : '—'),

                TextColumn::make('source_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                    ->placeholder('—'),

                TextColumn::make('source_name')
                    ->label('Source / Campaign')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('source_file_name')
                    ->label('File')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('phoneNumber.normalized_phone')
                    ->label('Phone')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Imported At')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options([
                        'ivr'      => 'IVR',
                        'whatsapp' => 'WhatsApp',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
