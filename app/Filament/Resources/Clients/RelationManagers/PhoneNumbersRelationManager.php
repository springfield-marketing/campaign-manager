<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\IvrNumbers\IvrNumberResource;
use App\Models\ClientPhoneNumber;
use App\Support\IvrSuppressionDisplay;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhoneNumbersRelationManager extends RelationManager
{
    protected static string $relationship = 'phoneNumbers';
    protected static ?string $title = 'Phone Numbers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('raw_phone')
                    ->label('Phone Number')
                    ->required()
                    ->maxLength(30)
                    ->helperText('Enter in international format, e.g. +971501234567'),

                Toggle::make('is_primary')
                    ->label('Primary Number')
                    ->default(false),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('normalized_phone')
                    ->label('Normalised Phone')
                    ->searchable()
                    ->url(fn (ClientPhoneNumber $record): ?string => $record->client_id
                        ? ClientResource::getUrl('edit', ['record' => $record->client_id])
                        : null),

                TextColumn::make('raw_phone')
                    ->label('Raw')
                    ->placeholder('—'),

                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),

                TextColumn::make('detected_country')
                    ->label('Country')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('usage_status')
                    ->label('Calling Status')
                    ->badge()
                    ->color(fn (?string $state) => IvrSuppressionDisplay::statusColor($state))
                    ->formatStateUsing(fn (?string $state) => IvrSuppressionDisplay::statusLabel($state))
                    ->placeholder('Ready to Call'),

                TextColumn::make('ivr_do_not_call_reason')
                    ->label('IVR Do Not Call Reason')
                    ->getStateUsing(fn (ClientPhoneNumber $record): string => self::activeIvrDoNotCallReason($record))
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),

                TextColumn::make('last_imported_at')
                    ->label('Last Import')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('ivrProfile.last_called_at')
                    ->label('Last IVR Call')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('ivr_call_records_count')
                    ->label('IVR Calls')
                    ->counts('ivrCallRecords')
                    ->sortable()
                    ->default(0),
            ])
            ->defaultSort('is_primary', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\Action::make('ivr_history')
                    ->label('IVR History')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->url(fn (ClientPhoneNumber $record): string =>
                        IvrNumberResource::getUrl('edit', ['record' => $record])
                    )
                    ->visible(fn (ClientPhoneNumber $record): bool => ($record->ivr_call_records_count ?? 0) > 0),
                DeleteAction::make(),
            ]);
    }

    private static function activeIvrDoNotCallReason(ClientPhoneNumber $record): string
    {
        $suppression = $record->suppressions()
            ->whereNull('released_at')
            ->where(fn ($query) => $query->whereNull('channel')->orWhere('channel', 'ivr'))
            ->latest('suppressed_at')
            ->first();

        return $suppression
            ? IvrSuppressionDisplay::reasonLabel($suppression->reason)
            : '—';
    }
}
