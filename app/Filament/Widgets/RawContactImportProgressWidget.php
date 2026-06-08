<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Models\IvrImport;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\HtmlString;

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

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(60)
                    ->tooltip(fn (?string $state) => $state)
                    ->color('danger')
                    ->placeholder('—')
                    ->visible(fn () => true),
            ])
            ->recordActions([
                Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->modalHeading(fn (IvrImport $record) => 'Import: '.$record->original_file_name)
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->form(fn (IvrImport $record) => [
                        Placeholder::make('summary')
                            ->label('Summary')
                            ->content(fn () => self::summaryHtml($record))
                            ->columnSpanFull(),

                        Placeholder::make('row_errors')
                            ->label('Row Errors')
                            ->content(fn () => self::rowErrorsHtml($record))
                            ->columnSpanFull()
                            ->visible(fn () => $record->errors()->exists()),
                    ])
                    ->action(fn () => null),
            ])
            ->toolbarActions([])
            ->paginated(false);
    }

    private static function summaryHtml(IvrImport $record): HtmlString
    {
        $rows = [
            ['Status',     ucwords(str_replace('_', ' ', $record->status))],
            ['File',       $record->original_file_name],
            ['Source',     $record->source_name ?? '—'],
            ['Total rows', number_format((int) $record->total_rows)],
            ['Imported',   number_format((int) $record->successful_rows)],
            ['Duplicates', number_format((int) $record->duplicate_rows)],
            ['Failed',     number_format((int) $record->failed_rows)],
            ['Staged',     number_format((int) ($record->summary['staged_rows'] ?? 0))],
            ['Started',    $record->started_at?->format('d M Y H:i:s') ?? '—'],
            ['Completed',  $record->completed_at?->format('d M Y H:i:s') ?? '—'],
        ];

        $trs = '';
        foreach ($rows as [$label, $value]) {
            $trs .= '<tr>
                <td style="padding:4px 12px 4px 0;font-weight:600;white-space:nowrap;color:#374151;vertical-align:top">'.e($label).'</td>
                <td style="padding:4px 0;color:#111827">'.e($value).'</td>
            </tr>';
        }

        $errorBlock = '';
        if ($record->error_message) {
            $errorBlock = '<div style="margin-top:12px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;color:#dc2626;font-size:13px">
                <strong>Import error:</strong> '.e($record->error_message).'
            </div>';
        }

        return new HtmlString('<table style="font-size:13px;border-collapse:collapse">'.$trs.'</table>'.$errorBlock);
    }

    private static function rowErrorsHtml(IvrImport $record): HtmlString
    {
        $errors = $record->errors()->orderBy('row_number')->limit(200)->get();

        if ($errors->isEmpty()) {
            return new HtmlString('<p style="font-size:13px;color:#6b7280">No row-level errors recorded.</p>');
        }

        $total   = $record->errors()->count();
        $showing = $errors->count();

        $rows = '';
        foreach ($errors as $err) {
            $payload = $err->row_payload
                ? implode(', ', array_filter(array_map(fn ($v) => trim((string) $v), (array) $err->row_payload)))
                : '—';

            $rows .= '<tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:4px 8px;white-space:nowrap;color:#6b7280;font-size:12px">'.(int) $err->row_number.'</td>
                <td style="padding:4px 8px;color:#dc2626;font-size:12px">'.e($err->error_message).'</td>
                <td style="padding:4px 8px;color:#9ca3af;font-size:11px;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'.e($payload).'">'.e($payload).'</td>
            </tr>';
        }

        $note = $total > $showing
            ? '<p style="margin-top:6px;font-size:12px;color:#9ca3af">Showing first '.$showing.' of '.number_format($total).' errors.</p>'
            : '';

        return new HtmlString('
            <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:6px">
                <table style="min-width:100%;border-collapse:collapse;font-size:12px">
                    <thead style="background:#f9fafb">
                        <tr>
                            <th style="padding:6px 8px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Row</th>
                            <th style="padding:6px 8px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Error</th>
                            <th style="padding:6px 8px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Row Data</th>
                        </tr>
                    </thead>
                    <tbody>'.$rows.'</tbody>
                </table>
            </div>
            '.$note
        );
    }
}
