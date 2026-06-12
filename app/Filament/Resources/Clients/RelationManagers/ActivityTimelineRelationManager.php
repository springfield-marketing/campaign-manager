<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivityTimelineRelationManager extends RelationManager
{
    protected static string $relationship = 'activityTimeline';

    protected static ?string $title = 'Activity Timeline';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('activity_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ivr' => 'IVR',
                        'whatsapp' => 'WhatsApp',
                        'manual' => 'Manual',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'ivr' => 'info',
                        'whatsapp' => 'success',
                        'manual' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('title')
                    ->label('Campaign / Source')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->badge()
                    ->placeholder('-')
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'answered', 'delivered', 'read', 'replied', 'sent' => 'success',
                        'missed', 'failed', 'undelivered' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('detail')
                    ->limit(120)
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options([
                        'ivr' => 'IVR',
                        'whatsapp' => 'WhatsApp',
                        'manual' => 'Manual notes',
                    ]),
            ])
            ->defaultSort('activity_at', 'desc')
            ->recordActions([])
            ->toolbarActions([])
            ->paginated([10, 25, 50]);
    }
}
