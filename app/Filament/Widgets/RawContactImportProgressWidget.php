<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Models\IvrImport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RawContactImportProgressWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Recent Imports';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                IvrImport::query()
                    ->where('type', IvrImportType::RawContacts)
                    ->latest()
                    ->limit(10)
            )
            ->poll('3s')
            ->columns([
                TextColumn::make('original_file_name')
                    ->label('File')
                    ->limit(40)
                    ->tooltip(fn (IvrImport $record) => $record->original_file_name),

                TextColumn::make('source_name')
                    ->label('Source')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completed'             => 'success',
                        'completed_with_errors' => 'warning',
                        'processing', 'pending' => 'info',
                        'failed'                => 'danger',
                        default                 => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(function (IvrImport $record): string {
                        if ($record->total_rows === 0 || $record->total_rows === null) {
                            return $record->status === 'pending' ? 'Queued' : '—';
                        }

                        $pct = min(100, (int) round($record->processed_rows / $record->total_rows * 100));

                        return "{$pct}%  ({$record->processed_rows} / {$record->total_rows})";
                    }),

                TextColumn::make('successful_rows')
                    ->label('Imported')
                    ->numeric()
                    ->color('success')
                    ->placeholder('—'),

                TextColumn::make('duplicate_rows')
                    ->label('Dupes')
                    ->numeric()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn (?int $state) => ($state ?? 0) > 0 ? 'danger' : 'gray')
                    ->placeholder('—'),

                TextColumn::make('staged_rows')
                    ->label('Staged')
                    ->getStateUsing(fn (IvrImport $record): ?int => $record->summary['staged_rows'] ?? null)
                    ->numeric()
                    ->color('warning')
                    ->placeholder('—'),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('d M H:i')
                    ->placeholder('—'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M H:i')
                    ->placeholder('—'),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->paginated(false);
    }
}
