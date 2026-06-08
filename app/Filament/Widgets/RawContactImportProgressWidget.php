<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Storage;

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
                    ->description(fn (IvrImport $r) => $r->source_name)
                    ->limit(40)
                    ->tooltip(fn (IvrImport $r) => $r->original_file_name),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending'               => 'Queued',
                        'processing'            => 'Processing',
                        'completed'             => 'Done',
                        'completed_with_errors' => 'Done (partial)',
                        'failed'                => 'Failed',
                        default                 => ucwords(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'completed'             => 'success',
                        'completed_with_errors' => 'warning',
                        'processing', 'pending' => 'info',
                        'failed'                => 'danger',
                        default                 => 'gray',
                    }),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(function (IvrImport $record): string {
                        if (in_array($record->status, ['pending', 'processing'], true)) {
                            if ($record->total_rows > 0) {
                                $pct = min(100, (int) round($record->processed_rows / $record->total_rows * 100));
                                return "{$pct}% — " . number_format($record->processed_rows) . ' / ' . number_format($record->total_rows);
                            }
                            return $record->status === 'pending' ? 'Waiting to start…' : 'Starting…';
                        }
                        if ($record->status === 'completed' || $record->status === 'completed_with_errors') {
                            return number_format($record->total_rows) . ' rows processed';
                        }
                        return '—';
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
                    ->label('Needs Review')
                    ->getStateUsing(fn (IvrImport $r): ?int => ($r->summary['staged_rows'] ?? null) ?: null)
                    ->numeric()
                    ->color('warning')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Started')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Try Again')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Retry this import?')
                    ->modalDescription('This will reset the import and run it again using the same file already on disk.')
                    ->visible(fn (IvrImport $r) => $r->status === IvrImportStatus::Failed->value
                        && $r->storage_path
                        && Storage::disk('local')->exists($r->storage_path)
                    )
                    ->action(function (IvrImport $record): void {
                        $record->update([
                            'status'          => IvrImportStatus::Pending,
                            'error_message'   => null,
                            'total_rows'      => 0,
                            'processed_rows'  => 0,
                            'successful_rows' => 0,
                            'failed_rows'     => 0,
                            'duplicate_rows'  => 0,
                            'started_at'      => null,
                            'completed_at'    => null,
                            'summary'         => array_merge(
                                is_array($record->summary) ? $record->summary : [],
                                ['staged_rows' => 0, 'staging_batch_id' => null],
                            ),
                        ]);

                        $record->broadcastProgress();
                        ProcessRawIvrImport::dispatch($record->id)->onQueue('imports');

                        Notification::make()
                            ->title('Import requeued.')
                            ->success()
                            ->send();
                    }),

                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->modalHeading(fn (IvrImport $r) => $r->original_file_name)
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->form(fn (IvrImport $record) => [
                        \Filament\Forms\Components\Placeholder::make('summary')
                            ->label('Summary')
                            ->content(fn () => self::summaryHtml($record))
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make('row_errors')
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

    private static function summaryHtml(IvrImport $record): \Illuminate\Support\HtmlString
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

        $errorBlock = $record->error_message
            ? '<div style="margin-top:10px;padding:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;color:#dc2626;font-size:13px"><strong>Error:</strong> '.e($record->error_message).'</div>'
            : '';

        return new \Illuminate\Support\HtmlString('<table style="font-size:13px;border-collapse:collapse">'.$trs.'</table>'.$errorBlock);
    }

    private static function rowErrorsHtml(IvrImport $record): \Illuminate\Support\HtmlString
    {
        $errors  = $record->errors()->orderBy('row_number')->limit(200)->get();
        $total   = $record->errors()->count();
        $showing = $errors->count();

        $rows = '';
        foreach ($errors as $err) {
            $payload = $err->row_payload
                ? e(implode(', ', array_filter(array_map(fn ($v) => trim((string) $v), (array) $err->row_payload))))
                : '—';

            $rows .= '<tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:4px 8px;color:#6b7280;font-size:12px;white-space:nowrap">'.(int) $err->row_number.'</td>
                <td style="padding:4px 8px;color:#dc2626;font-size:12px">'.e($err->error_message).'</td>
                <td style="padding:4px 8px;color:#9ca3af;font-size:11px;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'.$payload.'">'.$payload.'</td>
            </tr>';
        }

        $note = $total > $showing
            ? '<p style="margin-top:6px;font-size:12px;color:#9ca3af">Showing first '.$showing.' of '.number_format($total).' errors.</p>'
            : '';

        return new \Illuminate\Support\HtmlString('
            <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:6px">
                <table style="min-width:100%;border-collapse:collapse">
                    <thead style="background:#f9fafb">
                        <tr>
                            <th style="padding:6px 8px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Row</th>
                            <th style="padding:6px 8px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Error</th>
                            <th style="padding:6px 8px;text-align:left;font-size:11px;color:#6b7280;font-weight:600">Row data</th>
                        </tr>
                    </thead>
                    <tbody>'.$rows.'</tbody>
                </table>
            </div>'.$note
        );
    }
}
