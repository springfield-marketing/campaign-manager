<?php

namespace App\Filament\Resources\OfficialAreas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OfficialAreasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('emirate')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('area_name_en')
                    ->label('Official Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('source_area_id')
                    ->label('DLD ID')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('zone_id')
                    ->label('Zone')
                    ->formatStateUsing(fn (?int $state) => match($state) {
                        1 => 'Non-Freehold',
                        2 => 'Freehold',
                        default => '—',
                    })
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('emirate')
                    ->options([
                        'Dubai'          => 'Dubai',
                        'Abu Dhabi'      => 'Abu Dhabi',
                        'Sharjah'        => 'Sharjah',
                        'Ajman'          => 'Ajman',
                        'Ras Al Khaimah' => 'Ras Al Khaimah',
                        'Fujairah'       => 'Fujairah',
                        'Umm Al Quwain'  => 'Umm Al Quwain',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('area_name_en')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
