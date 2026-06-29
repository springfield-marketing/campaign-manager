<?php

namespace App\Filament\Resources\WhatsAppNumbers\Pages;

use App\Filament\Resources\WhatsAppNumbers\WhatsAppNumberResource;
use App\Filament\Widgets\WhatsAppNumberStatsWidget;
use App\Models\ActivityLog;
use App\Models\MarketingArea;
use App\Models\WhatsAppExportBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListWhatsAppNumbers extends ListRecords
{
    // Forwards the live table filter/search state to the header widgets (getWidgetData), so the
    // "Matching filters" widget's reactive props update when filters change. Without this the
    // widget only ever sees the default filter and its count never moves.
    use ExposesTableToWidgets;

    protected static string $resource = WhatsAppNumberResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            WhatsAppNumberStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Filtered CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    TextInput::make('batch_name')
                        ->label('Batch Name')
                        ->required()
                        ->placeholder('e.g. Binghatti March Campaign')
                        ->maxLength(255)
                        ->helperText('Give this export a name so you can exclude it from future exports.'),

                    TextInput::make('limit')
                        ->label('Number of records to export')
                        ->placeholder('Leave empty to export all')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Exports are randomised when a limit is set so you get a varied sample.'),

                    Select::make('exclude_batches')
                        ->label('Exclude numbers from previous batches')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => WhatsAppExportBatch::orderByDesc('created_at')
                            ->get()
                            ->mapWithKeys(fn (WhatsAppExportBatch $b) => [
                                $b->id => $b->name
                                    . ' — ' . number_format($b->record_count) . ' numbers'
                                    . ' (' . $b->created_at->format('d M Y') . ')',
                            ])
                            ->all()
                        )
                        ->helperText('Any number that appeared in the selected batches will be excluded from this export.'),
                ])
                ->action(function (array $data): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $limit          = filled($data['limit'] ?? null) ? (int) $data['limit'] : null;
                    $batchName      = $data['batch_name'];
                    $excludeBatchIds = $data['exclude_batches'] ?? [];

                    $filters = $this->tableFilters ?? [];

                    // EXP-001: start from the exact query the table is showing so the export can
                    // never drift out of sync with the on-screen filters. The previous version
                    // re-implemented a subset of filters by hand and silently dropped tags,
                    // uae_only, is_lead and suppressed — exporting the wrong numbers. Reusing the
                    // table's filtered query means any filter added to the table applies here for
                    // free. See docs/data-rules/exports.md.
                    $query = $this->getFilteredTableQuery();

                    // Exclude numbers that appeared in any of the selected previous batches
                    if (filled($excludeBatchIds)) {
                        $query->whereNotExists(fn ($q) => $q
                            ->selectRaw('1')
                            ->from('whatsapp_export_batch_numbers')
                            ->whereColumn('whatsapp_export_batch_numbers.client_phone_number_id', 'client_phone_numbers.id')
                            ->whereIn('whatsapp_export_batch_numbers.whatsapp_export_batch_id', $excludeBatchIds)
                        );
                    }

                    // EXP-001 compliance guard: never export an active WhatsApp suppression,
                    // regardless of which filters are set. The table's 'active' bucket already
                    // excludes these, but keep it hard here so an export can never message an
                    // unsubscribed contact. See docs/data-rules/exports.md.
                    $query->whereNotExists(fn ($q) => $q
                        ->selectRaw('1')
                        ->from('contact_suppressions')
                        ->whereColumn('contact_suppressions.client_phone_number_id', 'client_phone_numbers.id')
                        ->where('contact_suppressions.channel', 'whatsapp')
                        ->whereNull('contact_suppressions.released_at')
                    );

                    $ids = $query
                        ->select('client_phone_numbers.id')
                        ->distinct()
                        ->reorder()
                        ->pluck('id')
                        ->all();

                    if (empty($ids)) {
                        Notification::make()
                            ->title('No eligible numbers found matching the current filters.')
                            ->warning()
                            ->send();

                        // Return empty response to avoid streaming nothing
                        return response()->streamDownload(function (): void {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['phone_number']);
                            fclose($handle);
                        }, 'whatsapp_export_empty.csv', ['Content-Type' => 'text/csv']);
                    }

                    // Apply random sample if a limit was requested
                    if ($limit !== null && count($ids) > $limit) {
                        shuffle($ids);
                        $ids = array_slice($ids, 0, $limit);
                    }

                    // Persist the batch before streaming so the record exists even if
                    // the client disconnects mid-download.
                    $batch = WhatsAppExportBatch::create([
                        'name'            => $batchName,
                        'exported_by'     => auth()->id(),
                        'record_count'    => count($ids),
                        'filters_summary' => $this->buildFiltersSummary($filters, $excludeBatchIds),
                    ]);

                    ActivityLog::record('export.created', "Exported WhatsApp batch \"{$batchName}\" (".count($ids).' numbers)', $batch);

                    foreach (array_chunk($ids, 500) as $chunk) {
                        DB::table('whatsapp_export_batch_numbers')->insertOrIgnore(
                            array_map(fn (int $id): array => [
                                'whatsapp_export_batch_id' => $batch->id,
                                'client_phone_number_id'   => $id,
                            ], $chunk)
                        );
                    }

                    $slug     = Str::slug($batchName);
                    $fileName = 'whatsapp_export_' . now()->format('Y-m-d') . ($slug ? "_{$slug}" : '') . '.csv';

                    return response()->streamDownload(function () use ($ids): void {
                        set_time_limit(0);

                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['phone_number']);

                        foreach (array_chunk($ids, 1000) as $chunk) {
                            $phones = DB::table('client_phone_numbers')
                                ->whereIn('id', $chunk)
                                ->pluck('normalized_phone');

                            foreach ($phones as $phone) {
                                fputcsv($handle, [$phone]);
                            }
                        }

                        fclose($handle);
                    }, $fileName, ['Content-Type' => 'text/csv']);
                })
                ->modalHeading('Export WhatsApp Numbers')
                ->modalDescription('Exports exactly the numbers matching the filters currently applied to the table (unsubscribed numbers are always excluded). Numbers from selected previous batches are excluded to prevent overlap.'),

            Action::make('export_history')
                ->label('Export History')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->form(fn (): array => [
                    Placeholder::make('batches')
                        ->label('')
                        ->content(function (): string {
                            $batches = WhatsAppExportBatch::with('user')
                                ->orderByDesc('created_at')
                                ->limit(50)
                                ->get();

                            if ($batches->isEmpty()) {
                                return 'No exports yet.';
                            }

                            $lines = [];
                            foreach ($batches as $batch) {
                                $who     = $batch->user?->name ?? 'Unknown';
                                $when    = $batch->created_at->format('d M Y H:i');
                                $count   = number_format($batch->record_count);
                                $filters = $batch->filters_summary
                                    ? ' · ' . self::summariseFilters($batch->filters_summary)
                                    : '';

                                $lines[] = "#{$batch->id}  {$batch->name}  —  {$count} numbers  ·  {$when}  ·  {$who}{$filters}";
                            }

                            return implode("\n", $lines);
                        })
                        ->columnSpanFull(),
                ])
                ->modalHeading('Export History (last 50)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }

    private function buildFiltersSummary(array $filters, array $excludeBatchIds): array
    {
        $summary = [];

        $emirate = $filters['emirate']['value'] ?? null;
        if (filled($emirate)) {
            $summary['emirate'] = $emirate;
        }

        $communityIds = $filters['communities']['values'] ?? [];
        if (filled($communityIds)) {
            $summary['communities'] = MarketingArea::whereIn('id', $communityIds)
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        $waStatus = $filters['wa_status']['value'] ?? null;
        if (filled($waStatus)) {
            $summary['wa_status'] = $waStatus;
        }

        $campaignHistory = $filters['campaign_history']['value'] ?? null;
        if (filled($campaignHistory)) {
            $summary['campaign_history'] = $campaignHistory;
        }

        $lastMessageStatus = $filters['last_message_status']['value'] ?? null;
        if (filled($lastMessageStatus)) {
            $summary['last_message_status'] = $lastMessageStatus;
        }

        $country = $filters['country']['value'] ?? null;
        if (filled($country)) {
            $summary['country'] = $country;
        }

        if (filled($excludeBatchIds)) {
            $summary['excluded_batches'] = WhatsAppExportBatch::whereIn('id', $excludeBatchIds)
                ->orderBy('name')
                ->pluck('name')
                ->all();
        }

        return $summary;
    }

    private static function summariseFilters(array $summary): string
    {
        $parts = [];

        if (! empty($summary['emirate'])) {
            $parts[] = $summary['emirate'];
        }
        if (! empty($summary['communities'])) {
            $parts[] = implode(', ', $summary['communities']);
        }
        if (! empty($summary['wa_status'])) {
            $parts[] = 'status: ' . $summary['wa_status'];
        }
        if (! empty($summary['excluded_batches'])) {
            $parts[] = 'excluded: ' . implode(', ', $summary['excluded_batches']);
        }

        return implode(' · ', $parts);
    }
}
