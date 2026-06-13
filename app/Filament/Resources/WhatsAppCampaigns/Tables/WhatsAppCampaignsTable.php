<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WhatsAppCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('platform')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('total_messages')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('sent_count')
                    ->label('Sent')
                    ->numeric()
                    ->color('info'),

                TextColumn::make('delivered_count')
                    ->label('Delivered')
                    ->numeric()
                    ->color('success'),

                TextColumn::make('read_count')
                    ->label('Read')
                    ->numeric()
                    ->color('primary'),

                TextColumn::make('replied_count')
                    ->label('Replied')
                    ->numeric()
                    ->color('success'),

                TextColumn::make('failed_count')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn (?int $state) => ($state ?? 0) > 0 ? 'danger' : 'gray'),

                TextColumn::make('unsubscribed_count')
                    ->label('Unsubs')
                    ->numeric()
                    ->color('danger'),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('started_at', 'desc')
            ->recordActions([EditAction::make()]);
    }
}
