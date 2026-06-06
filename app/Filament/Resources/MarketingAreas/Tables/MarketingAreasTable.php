<?php

namespace App\Filament\Resources\MarketingAreas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MarketingAreasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('emirate')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Marketing Area')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('official_areas_count')
                    ->label('DLD Areas')
                    ->counts('officialAreas')
                    ->sortable(),

                TextColumn::make('projects_count')
                    ->label('Projects')
                    ->counts('projects')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('emirate')
                    ->options([
                        'Dubai'      => 'Dubai',
                        'Abu Dhabi'  => 'Abu Dhabi',
                        'Sharjah'    => 'Sharjah',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
