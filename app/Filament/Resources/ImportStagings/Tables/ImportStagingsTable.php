<?php

namespace App\Filament\Resources\ImportStagings\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImportStagingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_id')
                    ->label('Batch')
                    ->formatStateUsing(fn (string $state) => substr($state, 0, 8).'…')
                    ->copyable()
                    ->copyableState(fn (string $state) => $state)
                    ->tooltip(fn (string $state) => $state),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'matched'      => 'success',
                        'needs_review' => 'warning',
                        'rejected'     => 'danger',
                        default        => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('emirate')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('raw_marketing_area')
                    ->label('Marketing Area (raw)')
                    ->placeholder('—'),

                TextColumn::make('marketingArea.name')
                    ->label('Resolved Area')
                    ->placeholder('—')
                    ->color('success'),

                TextColumn::make('raw_project_name')
                    ->label('Project (raw)')
                    ->placeholder('—'),

                TextColumn::make('status_reason')
                    ->label('Issue')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn (?string $state) => $state),

                TextColumn::make('created_at')
                    ->label('Staged')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'      => 'Pending',
                        'matched'      => 'Matched',
                        'needs_review' => 'Needs Review',
                        'rejected'     => 'Rejected',
                    ]),

                SelectFilter::make('emirate')
                    ->options([
                        'Dubai'     => 'Dubai',
                        'Abu Dhabi' => 'Abu Dhabi',
                        'Sharjah'   => 'Sharjah',
                    ]),

                SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(fn () =>
                        \App\Models\ImportStaging::select('batch_id')
                            ->distinct()
                            ->orderByDesc('created_at')
                            ->pluck('batch_id', 'batch_id')
                            ->mapWithKeys(fn ($id) => [$id => substr($id, 0, 8).'…'])
                            ->all()
                    )
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([DeleteAction::make()])
            ->toolbarActions([]);
    }
}
