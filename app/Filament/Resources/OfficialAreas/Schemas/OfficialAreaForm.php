<?php

namespace App\Filament\Resources\OfficialAreas\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OfficialAreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('emirate')
                    ->options(self::emirates())
                    ->required()
                    ->searchable(),

                TextInput::make('area_name_en')
                    ->label('Official Area Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('source_area_id')
                    ->label('DLD Area ID')
                    ->numeric()
                    ->minValue(0),

                Select::make('zone_id')
                    ->label('Zone Type')
                    ->options([1 => 'Non-Freehold', 2 => 'Freehold'])
                    ->placeholder('Unknown'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function emirates(): array
    {
        return [
            'Dubai'          => 'Dubai',
            'Abu Dhabi'      => 'Abu Dhabi',
            'Sharjah'        => 'Sharjah',
            'Ajman'          => 'Ajman',
            'Ras Al Khaimah' => 'Ras Al Khaimah',
            'Fujairah'       => 'Fujairah',
            'Umm Al Quwain'  => 'Umm Al Quwain',
        ];
    }
}
