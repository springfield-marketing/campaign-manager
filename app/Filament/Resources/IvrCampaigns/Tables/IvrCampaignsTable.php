<?php

namespace App\Filament\Resources\IvrCampaigns\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IvrCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('state')
                    ->badge()
                    ->color(fn (?string $state) => match($state) {
                        'completed' => 'success',
                        'running'   => 'info',
                        'failed'    => 'danger',
                        default     => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('total_calls')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('answered_calls')
                    ->label('Answered')
                    ->numeric()
                    ->color('success'),

                TextColumn::make('leads_count')
                    ->label('Leads')
                    ->numeric()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('more_info_count')
                    ->label('More Info')
                    ->numeric(),

                TextColumn::make('unsubscribed_count')
                    ->label('Unsubs')
                    ->numeric()
                    ->color('danger'),

                TextColumn::make('script.name')
                    ->label('Script')
                    ->placeholder('—'),

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
