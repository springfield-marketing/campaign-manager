<?php

namespace App\Filament\Resources\Projects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
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
                    ->label('Project')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('marketingArea.name')
                    ->label('Marketing Area')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('officialArea.area_name_en')
                    ->label('DLD Area')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('developer_name')
                    ->label('Developer')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('buildings_count')
                    ->label('Buildings')
                    ->counts('buildings')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('emirate')
                    ->options([
                        'Dubai'     => 'Dubai',
                        'Abu Dhabi' => 'Abu Dhabi',
                        'Sharjah'   => 'Sharjah',
                    ]),

                SelectFilter::make('marketing_area_id')
                    ->label('Marketing Area')
                    ->relationship('marketingArea', 'name')
                    ->searchable()
                    ->preload(),

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
