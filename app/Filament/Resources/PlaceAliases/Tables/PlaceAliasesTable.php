<?php

namespace App\Filament\Resources\PlaceAliases\Tables;

use App\Models\Building;
use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlaceAliasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'official_area'  => 'Official Area',
                        'marketing_area' => 'Marketing Area',
                        'project'        => 'Project',
                        'building'       => 'Building',
                        default          => $state,
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('entity_id')
                    ->label('Entity')
                    ->formatStateUsing(function ($state, $record) {
                        return match($record->entity_type) {
                            'official_area'  => OfficialArea::find($state)?->area_name_en ?? "ID $state",
                            'marketing_area' => MarketingArea::find($state)?->name ?? "ID $state",
                            'project'        => Project::find($state)?->name ?? "ID $state",
                            'building'       => Building::find($state)?->name ?? "ID $state",
                            default          => "ID $state",
                        };
                    }),

                TextColumn::make('alias_name')
                    ->label('Alias')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('confidence_level')
                    ->label('Confidence')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'high'   => 'success',
                        'medium' => 'warning',
                        'low'    => 'danger',
                        default  => 'gray',
                    }),

                TextColumn::make('source')
                    ->label('Source')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->label('Type')
                    ->options([
                        'official_area'  => 'Official Area',
                        'marketing_area' => 'Marketing Area',
                        'project'        => 'Project',
                        'building'       => 'Building',
                    ]),

                SelectFilter::make('confidence_level')
                    ->label('Confidence')
                    ->options([
                        'high'   => 'High',
                        'medium' => 'Medium',
                        'low'    => 'Low',
                    ]),
            ])
            ->defaultSort('alias_name')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
