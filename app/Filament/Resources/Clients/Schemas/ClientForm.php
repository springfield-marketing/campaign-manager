<?php

namespace App\Filament\Resources\Clients\Schemas;

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
