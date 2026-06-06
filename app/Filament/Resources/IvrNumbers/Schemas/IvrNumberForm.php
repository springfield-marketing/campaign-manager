<?php

namespace App\Filament\Resources\IvrNumbers\Schemas;

use App\Models\ClientPhoneNumber;
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
            Section::make('Phone Number')
                ->columns(2)
                ->schema([
                    TextInput::make('normalized_phone')
                        ->label('Normalized Phone')
                        ->disabled(),

                    TextInput::make('raw_phone')
                        ->label('Raw Phone')
                        ->disabled(),

                    Placeholder::make('ivr_status')
                        ->label('IVR Status')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            ucfirst($record->ivrProfile?->usage_status ?? 'active')
                        ),

                    Placeholder::make('ivr_last_called')
                        ->label('Last Called')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->ivrProfile?->last_called_at?->format('d M Y') ?? '—'
                        ),

                    Placeholder::make('ivr_cooldown')
                        ->label('Cooldown Until')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            $record->ivrProfile?->cooldown_until?->format('d M Y') ?? '—'
                        ),

                    Placeholder::make('ivr_call_count')
                        ->label('Total IVR Calls')
                        ->content(fn (ClientPhoneNumber $record): string =>
                            (string) $record->ivrCallRecords()->count()
                        ),
                ]),

            Section::make('Client Details')
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
                            'Abu Dhabi' => 'Abu Dhabi',
                            'Dubai'     => 'Dubai',
                            'Sharjah'   => 'Sharjah',
                            'Ajman'     => 'Ajman',
                            'Umm Al Quwain' => 'Umm Al Quwain',
                            'Ras Al Khaimah' => 'Ras Al Khaimah',
                            'Fujairah'  => 'Fujairah',
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
                ]),
        ]);
    }
}
