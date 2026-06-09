<?php

namespace App\Filament\Resources\ImportStagings\Schemas;

use App\Models\ImportStaging;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportStagingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact Details')
                ->columns(3)
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->disabled(),

                    TextInput::make('phone')
                        ->label('Phone')
                        ->disabled()
                        ->placeholder('—'),

                    TextInput::make('email')
                        ->label('Email')
                        ->disabled()
                        ->placeholder('—'),

                    TextInput::make('emirate')
                        ->label('Emirate')
                        ->disabled()
                        ->placeholder('—'),

                    TextInput::make('country_iso')
                        ->label('Country')
                        ->disabled()
                        ->placeholder('—'),

                    TextInput::make('relationship_type')
                        ->label('Relationship Type')
                        ->disabled()
                        ->placeholder('—'),
                ]),

            Section::make('Location (Raw)')
                ->columns(2)
                ->description('Values as they appeared in the source file.')
                ->schema([
                    TextInput::make('raw_official_area')
                        ->label('Official Area')
                        ->disabled()
                        ->placeholder('—'),

                    TextInput::make('raw_marketing_area')
                        ->label('Marketing Area')
                        ->disabled()
                        ->placeholder('—'),

                    TextInput::make('raw_project_name')
                        ->label('Project')
                        ->disabled()
                        ->placeholder('—'),

                    TextInput::make('raw_building_name')
                        ->label('Building')
                        ->disabled()
                        ->placeholder('—'),

                    TextInput::make('raw_unit_reference')
                        ->label('Unit Reference')
                        ->disabled()
                        ->placeholder('—'),
                ]),

            Section::make('Location (Resolved)')
                ->columns(2)
                ->description('IDs resolved from master data during import.')
                ->schema([
                    Placeholder::make('marketing_area_resolved')
                        ->label('Marketing Area')
                        ->content(fn (ImportStaging $record): string => $record->marketingArea?->name ?? '—'),

                    Placeholder::make('official_area_resolved')
                        ->label('Official Area')
                        ->content(fn (ImportStaging $record): string => $record->officialArea?->area_name_en ?? '—'),

                    Placeholder::make('project_resolved')
                        ->label('Project')
                        ->content(fn (ImportStaging $record): string => $record->project?->name ?? '—'),

                    Placeholder::make('building_resolved')
                        ->label('Building')
                        ->content(fn (ImportStaging $record): string => $record->building?->name ?? '—'),
                ]),

            Section::make('Review Status')
                ->columns(2)
                ->schema([
                    TextInput::make('status')
                        ->label('Status')
                        ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                        ->disabled(),

                    TextInput::make('source')
                        ->label('Source')
                        ->disabled()
                        ->placeholder('—'),

                    Placeholder::make('status_reason')
                        ->label('Reason')
                        ->content(fn (ImportStaging $record): string => $record->status_reason ?? '—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
