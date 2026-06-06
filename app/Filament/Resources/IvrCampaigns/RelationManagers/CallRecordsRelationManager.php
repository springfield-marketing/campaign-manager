<?php

namespace App\Filament\Resources\IvrCampaigns\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CallRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'callRecords';
    protected static ?string $title = 'Call Records';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phoneNumber.normalized_phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('call_status')
                    ->label('Outcome')
                    ->badge()
                    ->color(fn (?string $state) => match($state) {
                        'answered'  => 'success',
                        'missed', 'no_answer' => 'warning',
                        'failed'    => 'danger',
                        default     => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('dtmf_outcome')
                    ->label('DTMF')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('call_time')
                    ->label('Call Time')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('total_duration_seconds')
                    ->label('Duration (s)')
                    ->numeric()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('call_status')
                    ->label('Outcome')
                    ->options([
                        'answered' => 'Answered',
                        'missed'   => 'Missed',
                        'no_answer'=> 'No Answer',
                        'failed'   => 'Failed',
                    ]),
            ])
            ->defaultSort('call_time', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
