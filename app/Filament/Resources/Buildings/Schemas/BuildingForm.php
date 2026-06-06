<?php

namespace App\Filament\Resources\Buildings\Schemas;

use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\Project;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BuildingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('emirate')
                    ->options([
                        'Dubai'     => 'Dubai',
                        'Abu Dhabi' => 'Abu Dhabi',
                        'Sharjah'   => 'Sharjah',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('name')
                    ->label('Building / Tower Name')
                    ->required()
                    ->maxLength(255),

                Select::make('project_id')
                    ->label('Project')
                    ->options(fn ($get) => Project::when(
                        $get('emirate'),
                        fn ($q, $e) => $q->where('emirate', $e)
                    )->active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live(),

                Select::make('marketing_area_id')
                    ->label('Marketing Area')
                    ->options(fn ($get) => MarketingArea::when(
                        $get('emirate'),
                        fn ($q, $e) => $q->where('emirate', $e)
                    )->active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                Select::make('official_area_id')
                    ->label('Official DLD Area')
                    ->options(fn ($get) => OfficialArea::when(
                        $get('emirate'),
                        fn ($q, $e) => $q->where('emirate', $e)
                    )->active()->orderBy('area_name_en')->pluck('area_name_en', 'id'))
                    ->searchable()
                    ->nullable(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
