<?php

namespace App\Filament\Resources\IvrNumbers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IvrCallRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'ivrCallRecords';
    protected static ?string $title = 'IVR Call History';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('call_time')
                    ->label('Date & Time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('call_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match (strtolower((string) $state)) {
                        'answered' => 'success',
                        'missed', 'no_answer', 'unanswered' => 'warning',
                        'failed'   => 'danger',
                        default    => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('dtmf_outcome')
                    ->label('DTMF Outcome')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'interested'  => 'success',
                        'more_info'   => 'info',
                        'unsubscribe' => 'danger',
                        default       => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('total_duration_seconds')
                    ->label('Duration (s)')
                    ->numeric()
                    ->placeholder('—'),

                TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->placeholder('—')
                    ->limit(20),
            ])
            ->defaultSort('call_time', 'desc')
            ->filters([
                SelectFilter::make('call_status')
                    ->label('Status')
                    ->options([
                        'Answered'  => 'Answered',
                        'Missed'    => 'Missed',
                        'Failed'    => 'Failed',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
