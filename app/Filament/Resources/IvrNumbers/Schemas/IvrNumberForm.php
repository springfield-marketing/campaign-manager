<?php

namespace App\Filament\Resources\IvrNumbers\Schemas;

use App\Models\ClientPhoneNumber;
use App\Support\IvrSuppressionDisplay;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IvrNumberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact')
                ->description('Changes here update the linked contact record.')
                ->columns(2)
                ->schema([
                    TextInput::make('client_full_name')
                        ->label('Full Name')
                        ->maxLength(255),

                    TextInput::make('client_email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),

                    Select::make('client_emirate')
                        ->label('Emirate')
                        ->options([
                            'Abu Dhabi'      => 'Abu Dhabi',
                            'Dubai'          => 'Dubai',
                            'Sharjah'        => 'Sharjah',
                            'Ajman'          => 'Ajman',
                            'Umm Al Quwain'  => 'Umm Al Quwain',
                            'Ras Al Khaimah' => 'Ras Al Khaimah',
                            'Fujairah'       => 'Fujairah',
                        ])
                        ->nullable(),

                    TextInput::make('client_nationality')
                        ->label('Nationality')
                        ->maxLength(100),

                    Select::make('client_gender')
                        ->label('Gender')
                        ->options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'])
                        ->nullable(),

                    TextInput::make('client_interest')
                        ->label('Interest')
                        ->maxLength(255),

                    Placeholder::make('client_tags')
                        ->label('Tags')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->client?->tags->pluck('name')->join(', ') ?: '—'
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Number Details')
                ->columns(2)
                ->schema([
                    TextInput::make('normalized_phone')
                        ->label('Normalized Phone')
                        ->disabled(),

                    TextInput::make('raw_phone')
                        ->label('Raw Phone')
                        ->disabled(),

                    Placeholder::make('ivr_status')
                        ->label('Calling Status')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            IvrSuppressionDisplay::statusLabel($record->ivrProfile?->usage_status)
                        ),

                    Placeholder::make('do_not_call_status')
                        ->label('Do Not Call')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            self::activeSuppression($record)
                                ? 'Yes — ' . IvrSuppressionDisplay::reasonLabel(self::activeSuppression($record)?->reason)
                                : 'No'
                        ),

                    Placeholder::make('do_not_call_source')
                        ->label('Do Not Call Source')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            self::activeSuppression($record)
                                ? IvrSuppressionDisplay::sourceLabel(self::activeSuppression($record))
                                : '—'
                        ),

                    Placeholder::make('do_not_call_details')
                        ->label('Do Not Call Details')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            self::activeSuppression($record)
                                ? IvrSuppressionDisplay::detailLabel(self::activeSuppression($record))
                                : '—'
                        ),

                    Placeholder::make('ivr_last_called')
                        ->label('Last Called')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->ivrProfile?->last_called_at?->format('d M Y') ?? '—'
                        ),

                    Placeholder::make('ivr_cooldown')
                        ->label('Rest Until')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->ivrProfile?->cooldown_until?->format('d M Y') ?? '—'
                        ),

                    Placeholder::make('ivr_call_count')
                        ->label('Total IVR Calls')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            (string) $record->ivrCallRecords()->count()
                        ),
                ]),
        ]);
    }

    private static function activeSuppression(ClientPhoneNumber $record): ?\App\Models\ContactSuppression
    {
        return $record->suppressions()->activeIvr()->latest('suppressed_at')->first();
    }
}
