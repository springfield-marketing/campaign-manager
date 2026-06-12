<?php

namespace App\Filament\Resources\IvrCampaigns\RelationManagers;

use App\Filament\Resources\Clients\ClientResource;
use App\Modules\IVR\Models\IvrCallRecord;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CallRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'callRecords';
    protected static ?string $title = 'Lead Call Records';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereIn('dtmf_outcome', ['interested', 'more_info'])
                ->with(['phoneNumber.client.primaryEmail']))
            ->columns([
                TextColumn::make('call_time')
                    ->label('Time')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('phoneNumber.normalized_phone')
                    ->label('Phone')
                    ->searchable()
                    ->url(fn (IvrCallRecord $record): ?string => $record->phoneNumber?->client_id
                        ? ClientResource::getUrl('edit', ['record' => $record->phoneNumber->client_id])
                        : null)
                    ->placeholder('-'),

                TextColumn::make('phoneNumber.client.full_name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('phoneNumber.client.primaryEmail.email')
                    ->label('Email')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('phoneNumber.client.emirate')
                    ->label('Emirate')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('phoneNumber.client.original_source')
                    ->label('Source')
                    ->placeholder('-')
                    ->limit(28)
                    ->toggleable(),

                TextColumn::make('call_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match (strtolower((string) $state)) {
                        'answered' => 'success',
                        'missed', 'no_answer', 'unanswered' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-'),

                TextColumn::make('dtmf_outcome')
                    ->label('Response')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                    ->color(fn (?string $state) => match ($state) {
                        'interested' => 'success',
                        'more_info' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('total_duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state): string => $state ? gmdate('H:i:s', $state) : '-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('call_status')
                    ->label('Status')
                    ->options([
                        'Answered' => 'Answered',
                        'Missed' => 'Missed',
                        'Failed' => 'Failed',
                    ]),

                SelectFilter::make('dtmf_outcome')
                    ->label('Response')
                    ->options([
                        'interested' => 'Interested',
                        'more_info' => 'More info',
                    ]),
            ])
            ->defaultSort('call_time', 'desc')
            ->headerActions([
                Action::make('export_leads')
                    ->label('Export Leads')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (): string => route('ivr.campaign-leads.export', $this->getOwnerRecord())),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
