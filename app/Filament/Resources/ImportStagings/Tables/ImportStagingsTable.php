<?php

namespace App\Filament\Resources\ImportStagings\Tables;

use App\Models\ImportStaging;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ImportStagingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_id')
                    ->label('Batch')
                    ->formatStateUsing(fn (string $state) => self::batchLabel($state))
                    ->copyable()
                    ->copyableState(fn (string $state) => $state)
                    ->tooltip(fn (string $state) => $state),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'matched'      => 'success',
                        'needs_review' => 'warning',
                        'rejected'     => 'danger',
                        default        => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state)))
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        'Dubai'          => 'Dubai',
                        'Abu Dhabi'      => 'Abu Dhabi',
                        'Sharjah'        => 'Sharjah',
                        'Ajman'          => 'Ajman',
                        'Ras Al Khaimah' => 'Ras Al Khaimah',
                        'Fujairah'       => 'Fujairah',
                        'Umm Al Quwain'  => 'Umm Al Quwain',
                    ]),

                SelectFilter::make('batch_id')
                    ->label('Batch / Import')
                    ->options(fn () =>
                        // GROUP BY + MAX avoids the PostgreSQL DISTINCT+ORDER BY restriction
                        ImportStaging::select('batch_id', DB::raw('max(created_at) as latest_at'))
                            ->groupBy('batch_id')
                            ->orderByDesc('latest_at')
                            ->pluck('batch_id', 'batch_id')
                            ->mapWithKeys(fn ($id) => [$id => self::batchLabel($id)])
                            ->all()
                    )
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([DeleteAction::make()])
            ->toolbarActions([]);
    }

    private static function batchLabel(string $batchId): string
    {
        // "raw-import-42" → "Import #42"
        if (preg_match('/^raw-import-(\d+)$/', $batchId, $m)) {
            return 'Import #'.$m[1];
        }

        return substr($batchId, 0, 12).(strlen($batchId) > 12 ? '…' : '');
    }
}
