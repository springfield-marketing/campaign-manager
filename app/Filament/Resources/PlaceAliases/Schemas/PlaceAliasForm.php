<?php

namespace App\Filament\Resources\PlaceAliases\Schemas;

use App\Models\Building;
use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\Project;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlaceAliasForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('entity_type')
                    ->label('Entity Type')
                    ->options([
                        'official_area'  => 'Official Area',
                        'marketing_area' => 'Marketing Area',
                        'project'        => 'Project',
                        'building'       => 'Building',
                    ])
                    ->required()
                    ->live(),

                Select::make('entity_id')
                    ->label('Entity')
                    ->options(fn ($get) => self::entityOptions($get('entity_type')))
                    ->searchable()
                    ->required(),

                TextInput::make('alias_name')
                    ->label('Alias')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The alternative name people use (e.g. "JLT", "Marina")'),

                Select::make('confidence_level')
                    ->label('Confidence')
                    ->options([
                        'high'   => 'High',
                        'medium' => 'Medium',
                        'low'    => 'Low',
                    ])
                    ->default('high'),

                TextInput::make('source')
                    ->label('Source')
                    ->maxLength(100)
                    ->placeholder('seed / manual / import'),
            ]),
        ]);
    }

    private static function entityOptions(?string $type): array
    {
        return match($type) {
            'official_area'  => OfficialArea::active()->orderBy('area_name_en')->pluck('area_name_en', 'id')->all(),
            'marketing_area' => MarketingArea::active()->orderBy('name')->pluck('name', 'id')->all(),
            'project'        => Project::active()->orderBy('name')->pluck('name', 'id')->all(),
            'building'       => Building::active()->orderBy('name')->pluck('name', 'id')->all(),
            default          => [],
        };
    }
}
