<?php

namespace App\Filament\Resources\IvrImports\Tables;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessIvrCampaignResultsImport;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Jobs\ProcessUnsubscriberImport;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Models\IvrScript;
use App\Modules\IVR\Support\CampaignResultsReverter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class IvrImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('4s')
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'campaign_results' => 'success',
                        'unsubscribers'    => 'warning',
                        default            => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => IvrImportType::tryFrom($state)?->getLabel() ?? ucwords(str_replace('_', ' ', $state))),

                TextColumn::make('original_file_name')
                    ->label('File')
                    ->searchable()
                    ->limit(35),

                TextColumn::make('source_name')
                    ->label('Source / Campaign')
                    ->getStateUsing(fn (IvrImport $record): ?string =>
                        $record->source_name
                        ?? ($record->type === 'campaign_results'
                            ? data_get($record->summary, 'order_number')
                            : null)
                    )
                    ->placeholder('—')
                    ->searchable(query: fn ($query, $search) =>
                        $query->where(fn ($q) =>
                            $q->where('source_name', 'like', "%{$search}%")
                              ->orWhere('original_file_name', 'like', "%{$search}%")
                        )
                    ),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completed'             => 'success',
                        'completed_with_errors' => 'warning',
                        'processing'            => 'info',
                        'failed', 'delete_failed', 'revert_failed' => 'danger',
                        default                 => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => IvrImportStatus::tryFrom($state)?->getLabel() ?? ucwords(str_replace('_', ' ', $state))),

                TextColumn::make('total_rows')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('successful_rows')
                    ->label('OK')
                    ->numeric()
                    ->color('success'),

                TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn (?int $state) => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('duplicate_rows')
                    ->label('Dupes')
                    ->numeric()
                    ->color('gray'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(IvrImportType::class),

                SelectFilter::make('status')
                    ->options(IvrImportStatus::class),
            ])
            ->headerActions([
                // ── Campaign results upload ────────────────────────────────
                Action::make('upload_results')
                    ->label('Upload Campaign Results')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->form([
                        FileUpload::make('file')
                            ->label('Campaign CSV')
                            ->required()
                            ->disk('local')
                            ->directory('ivr/imports/results/tmp')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(51200),

                        Select::make('ivr_script_id')
                            ->label('Script (optional)')
                            ->options(fn () => IvrScript::orderBy('name')->pluck('name', 'id'))
                            ->nullable()
                            ->placeholder('— No script —')
                            ->searchable(),
                    ])
                    ->action(function (array $data): void {
                        $tmpRelative   = $data['file'];
                        $originalName  = basename($tmpRelative);
                        $finalRelative = 'ivr/imports/results/' . $originalName;

                        if (IvrImport::where('type', IvrImportType::CampaignResults->value)->where('original_file_name', $originalName)->whereNull('reverted_at')->exists()) {
                            Notification::make()
                                ->title("A campaign results import named \"{$originalName}\" already exists. Rename the file if this is intentionally a new upload.")
                                ->danger()->send();
                            return;
                        }

                        Storage::disk('local')->move($tmpRelative, $finalRelative);

                        $import = IvrImport::create([
                            'type'               => IvrImportType::CampaignResults,
                            'status'             => IvrImportStatus::Pending,
                            'original_file_name' => $originalName,
                            'stored_file_name'   => $originalName,
                            'storage_path'       => $finalRelative,
                            'ivr_script_id'      => $data['ivr_script_id'] ?: null,
                            'uploaded_by'        => auth()->id(),
                        ]);

                        $import->broadcastProgress();
                        ProcessIvrCampaignResultsImport::dispatch($import->id)->onQueue('imports-high');

                        Notification::make()->title('Campaign results import queued — status will update automatically')->success()->send();
                    })
                    ->modalHeading('Upload Campaign Results CSV')
                    ->modalDescription('Upload the campaign results CSV exported from your IVR platform. Optionally link a script for reference.')
                    ->modalSubmitActionLabel('Upload & Queue'),

                Action::make('upload_unsubscribers')
                    ->label('Upload Do Not Call CSV')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->form([
                        FileUpload::make('file')
                            ->label('Do Not Call CSV')
                            ->required()
                            ->disk('local')
                            ->directory('ivr/imports/unsubscribers/tmp')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(10240),
                    ])
                    ->action(function (array $data): void {
                        $tmpRelative   = $data['file'];
                        $originalName  = basename($tmpRelative);
                        $finalRelative = 'ivr/imports/unsubscribers/' . $originalName;

                        Storage::disk('local')->move($tmpRelative, $finalRelative);

                        $import = IvrImport::create([
                            'type'               => IvrImportType::Unsubscribers,
                            'status'             => IvrImportStatus::Pending,
                            'original_file_name' => $originalName,
                            'stored_file_name'   => $originalName,
                            'storage_path'       => $finalRelative,
                            'uploaded_by'        => auth()->id(),
                            'summary'            => ['format' => 'phone,name'],
                        ]);

                        $import->broadcastProgress();
                        ProcessUnsubscriberImport::dispatch($import->id)->onQueue('imports');

                        Notification::make()->title('Do Not Call import queued — status will update automatically')->success()->send();
                    })
                    ->modalHeading('Upload IVR Do Not Call CSV')
                    ->modalDescription('Upload a CSV with phone number in the first column and optional name in the second column.')
                    ->modalSubmitActionLabel('Upload & Queue'),
            ])
            ->recordActions([
                Action::make('view_errors')
                    ->label(fn (IvrImport $record) => 'Errors (' . $record->failed_rows . ')')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (IvrImport $record) => ($record->failed_rows ?? 0) > 0)
                    ->form(fn (IvrImport $record): array => [
                        Textarea::make('error_details')
                            ->label("Showing first 20 of {$record->failed_rows} failed row(s)")
                            ->default(self::formatErrors($record))
                            ->rows(22)
                            ->disabled(),
                    ])
                    ->modalHeading(fn (IvrImport $record) => "Import Errors — {$record->original_file_name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('4xl'),

                Action::make('reprocess')
                    ->label(fn (IvrImport $record) => $record->status === IvrImportStatus::Processing->value ? 'Unlock & Re-process' : 'Re-process')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (IvrImport $record) =>
                        in_array($record->status, [
                            IvrImportStatus::Completed->value,
                            IvrImportStatus::CompletedWithErrors->value,
                            IvrImportStatus::Failed->value,
                            IvrImportStatus::Processing->value,
                        ])
                        && $record->storage_path
                        && file_exists(storage_path('app/private/' . $record->storage_path))
                    )
                    ->requiresConfirmation()
                    ->modalHeading(fn (IvrImport $record) => $record->status === IvrImportStatus::Processing->value ? 'Unlock stuck import?' : 'Re-process this import?')
                    ->modalDescription(fn (IvrImport $record) => $record->status === IvrImportStatus::Processing->value
                        ? 'This import appears stuck (the job timed out before it could update its status). It will be reset and re-queued from the beginning. Existing records will be updated or counted as duplicates.'
                        : 'The import will be reset to pending and re-queued. Existing records will be updated or counted as duplicates.'
                    )
                    ->action(function (IvrImport $record): void {
                        $record->errors()->delete();
                        $record->update([
                            'status'          => IvrImportStatus::Pending->value,
                            'error_message'   => null,
                            'total_rows'      => 0,
                            'processed_rows'  => 0,
                            'successful_rows' => 0,
                            'failed_rows'     => 0,
                            'duplicate_rows'  => 0,
                            'started_at'      => null,
                            'completed_at'    => null,
                        ]);
                        match ($record->type) {
                            'campaign_results' => ProcessIvrCampaignResultsImport::dispatch($record->id)->onQueue('imports-high'),
                            'unsubscribers'    => ProcessUnsubscriberImport::dispatch($record->id)->onQueue('imports'),
                            'raw_contacts'     => ProcessRawIvrImport::dispatch($record->id)->onQueue('imports'),
                            default            => ProcessIvrCampaignResultsImport::dispatch($record->id)->onQueue('imports-high'),
                        };
                        Notification::make()->title('Re-queued — watch status update below')->warning()->send();
                    }),

                Action::make('revert_campaign_results')
                    ->label('Revert Campaign Results')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (IvrImport $record) =>
                        $record->type === IvrImportType::CampaignResults->value
                        && $record->reverted_at === null
                        && ! in_array($record->status, [
                            IvrImportStatus::Pending->value,
                            IvrImportStatus::Processing->value,
                        ], true)
                    )
                    ->form([
                        Textarea::make('revert_reason')
                            ->label('Revert reason')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Revert this campaign import?')
                    ->modalDescription('This will remove its campaign results, related campaign-only data, and recalculate affected numbers.')
                    ->action(function (IvrImport $record, array $data): void {
                        app(CampaignResultsReverter::class)->revert(
                            import: $record,
                            userId: auth()->id(),
                            reason: $data['revert_reason'] ?? null,
                        );

                        Notification::make()
                            ->title("Campaign import {$record->original_file_name} was reverted.")
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->visible(fn (IvrImport $record) =>
                        $record->type === IvrImportType::Unsubscribers->value
                        && ! in_array($record->status, [
                            IvrImportStatus::Processing->value,
                            IvrImportStatus::Deleting->value,
                        ], true)
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function formatErrors(IvrImport $record): string
    {
        $errors = $record->errors()->orderBy('row_number')->limit(20)->get();
        $lines  = [];

        foreach ($errors as $e) {
            $payload = is_string($e->row_payload) ? json_decode($e->row_payload, true) : ($e->row_payload ?? []);
            $preview = is_array($payload)
                ? implode(' | ', array_slice(array_values(array_filter($payload, fn ($v) => (string) $v !== '')), 0, 6))
                : '';

            $lines[] = "Row {$e->row_number}: {$e->error_message}";
            if ($preview) {
                $lines[] = "  → {$preview}";
            }
            $lines[] = '';
        }

        if ($record->failed_rows > 20) {
            $lines[] = '... and ' . ($record->failed_rows - 20) . ' more rows with similar errors.';
        }

        return trim(implode("\n", $lines));
    }
}
