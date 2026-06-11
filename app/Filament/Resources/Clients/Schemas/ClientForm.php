<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')->columns(2)->schema([
                TextInput::make('full_name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                Select::make('gender')
                    ->options([
                        'male'   => 'Male',
                        'female' => 'Female',
                        'other'  => 'Other',
                    ])
                    ->nullable(),

                TextInput::make('nationality')
                    ->maxLength(100),

                TextInput::make('interest')
                    ->label('Interest / Project Interest')
                    ->maxLength(255),
            ]),

            Section::make('Location')->columns(2)->schema([
                Select::make('emirate')
                    ->options([
                        'Dubai'          => 'Dubai',
                        'Abu Dhabi'      => 'Abu Dhabi',
                        'Sharjah'        => 'Sharjah',
                        'Ajman'          => 'Ajman',
                        'Ras Al Khaimah' => 'Ras Al Khaimah',
                        'Fujairah'       => 'Fujairah',
                        'Umm Al Quwain'  => 'Umm Al Quwain',
                    ])
                    ->nullable()
                    ->searchable(),

                TextInput::make('country_iso')
                    ->label('Country ISO')
                    ->maxLength(2)
                    ->placeholder('AE')
                    ->helperText('2-letter ISO code e.g. AE, GB, IN'),
            ]),

            Section::make('Tier & Scoring')
                ->columns(4)
                ->description('Tier can be set manually. Scores are auto-computed from property data and improve with each import.')
                ->schema([
                    Select::make('tier')
                        ->options(Client::TIERS)
                        ->nullable()
                        ->placeholder('Auto (from score)')
                        ->helperText('Leave blank to auto-assign from wealth score'),

                    Placeholder::make('wealth_score')
                        ->label('Wealth Score')
                        ->content(fn (Client $record): string => $record->wealth_score !== null
                            ? $record->wealth_score . ' / 100'
                            : 'Not yet scored'),

                    Placeholder::make('completeness_score')
                        ->label('Completeness')
                        ->content(fn (Client $record): string => $record->completeness_score !== null
                            ? $record->completeness_score . '%'
                            : 'Not yet scored'),
                ]),

            Section::make('Tags')->schema([
                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required()->maxLength(100),
                    ])
                    ->label('Tags'),
            ]),
        ]);
    }
}
