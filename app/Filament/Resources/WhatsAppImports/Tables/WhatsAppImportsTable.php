<?php

namespace App\Filament\Resources\WhatsAppImports\Tables;

use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppImportType;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppCampaignResultsImport;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppRawImport;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppUnsubscriberImport;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class WhatsAppImportsTable
{
    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'campaign_results' => 'Campaign Results',
            'unsubscribers'    => 'Unsubscribers',
            default            => ucwords(str_replace('_', ' ', $type)),
        };
    }

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
                    ->formatStateUsing(fn (string $state) => self::typeLabel($state)),

                TextColumn::make('original_file_name')
                    ->label('File')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('source_name')
                    ->label('Source')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'completed'             => 'success',
                        'completed_with_errors' => 'warning',
                        'processing'            => 'info',
                        'draft'                 => 'gray',
                        'failed', 'delete_failed', 'revert_failed' => 'danger',
                        default                 => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),

                TextColumn::make('total_rows')->label('Total')->numeric()->sortable(),
                TextColumn::make('successful_rows')->label('OK')->numeric()->color('success'),
                TextColumn::make('failed_rows')->label('Failed')->numeric()
                    ->color(fn (?int $state) => ($state ?? 0) > 0 ? 'danger' : 'gray'),

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
                    ->options([
                        'campaign_results' => 'Campaign Results',
                        'unsubscribers'    => 'Unsubscribers',
                    ]),

                SelectFilter::make('status')
                    ->options(array_column(
                        array_map(fn ($c) => [
                            'value' => $c->value,
                            'label' => ucwords(str_replace('_', ' ', $c->value)),
                        ], WhatsAppImportStatus::cases()),
                        'label', 'value'
                    )),
            ])
            ->headerActions([
                Action::make('upload_campaign_results')
                    ->label('Upload Campaign Results')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->form([
                        FileUpload::make('file')
                            ->label('Campaign CSV')
                            ->required()
                            ->disk('local')
                            ->directory('whatsapp/imports/campaign-results/tmp')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(51200),

                        \Filament\Forms\Components\TextInput::make('platform')
                            ->label('Platform')
                            ->placeholder('e.g. WATI, Zoko, Twilio')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $originalName  = $data['file'];
                        $tmpRelative   = 'whatsapp/imports/campaign-results/tmp/' . $originalName;
                        $finalRelative = 'whatsapp/imports/campaign-results/' . $originalName;

                        $existing = WhatsAppImport::query()
                            ->where('type', WhatsAppImportType::CampaignResults->value)
                            ->where('original_file_name', $originalName)
                            ->whereNull('reverted_at')
                            ->whereNotIn('status', [
                                WhatsAppImportStatus::Failed->value,
                                WhatsAppImportStatus::CompletedWithErrors->value,
                            ])
                            ->exists();

                        if ($existing) {
                            Notification::make()
                                ->title("A campaign results import named \"{$originalName}\" already exists.")
                                ->body('Delete or revert it first if you want to re-import.')
                                ->danger()
                                ->send();
                            return;
                        }

                        Storage::disk('local')->move($tmpRelative, $finalRelative);

                        $import = WhatsAppImport::create([
                            'type'                => WhatsAppImportType::CampaignResults,
                            'status'              => WhatsAppImportStatus::Pending,
                            'original_file_name'  => $originalName,
                            'stored_file_name'    => $originalName,
                            'storage_path'        => $finalRelative,
                            'source_name'         => $data['platform'] ?: null,
                            'uploaded_by'         => auth()->id(),
                        ]);

                        $import->broadcastProgress();
                        ProcessWhatsAppCampaignResultsImport::dispatch($import->id)->onQueue('imports');

                        Notification::make()->title('Campaign results import queued — status updates automatically')->success()->send();
                    })
                    ->modalHeading('Upload WhatsApp Campaign Results CSV')
                    ->modalSubmitActionLabel('Upload & Queue'),

                Action::make('upload_unsubscribers')
                    ->label('Upload Unsubscribers')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->form([
                        FileUpload::make('file')
                            ->label('Unsubscribers CSV')
                            ->required()
                            ->disk('local')
                            ->directory('whatsapp/unsubscribers/tmp')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(10240),
                    ])
                    ->action(function (array $data): void {
                        $originalName = $data['file'];
                        $tmpRelative = 'whatsapp/unsubscribers/tmp/' . $originalName;
                        $finalRelative = 'whatsapp/unsubscribers/' . $originalName;

                        Storage::disk('local')->move($tmpRelative, $finalRelative);

                        $import = WhatsAppImport::create([
                            'type'               => WhatsAppImportType::Unsubscribers,
                            'status'             => WhatsAppImportStatus::Pending,
                            'original_file_name' => $originalName,
                            'stored_file_name'   => $originalName,
                            'storage_path'       => $finalRelative,
                            'uploaded_by'        => auth()->id(),
                        ]);

                        ProcessWhatsAppUnsubscriberImport::dispatch($import->id)->onQueue('imports');

                        Notification::make()->title('Unsubscriber import queued — status updates automatically')->success()->send();
                    })
                    ->modalHeading('Upload WhatsApp Unsubscribers CSV')
                    ->modalDescription('Upload a CSV with phone number in the first column and optional name in the second column.')
                    ->modalSubmitActionLabel('Upload & Queue'),
            ])
            ->recordActions([
                Action::make('view_errors')
                    ->label(fn (WhatsAppImport $record) => 'Errors (' . $record->failed_rows . ')')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (WhatsAppImport $record) => ($record->failed_rows ?? 0) > 0)
                    ->form(fn (WhatsAppImport $record): array => [
                        Textarea::make('error_details')
                            ->label("Showing first 20 of {$record->failed_rows} failed row(s)")
                            ->default(self::formatErrors($record))
                            ->rows(22)
                            ->disabled(),
                    ])
                    ->modalHeading(fn (WhatsAppImport $record) => "Import Errors — {$record->original_file_name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('4xl'),

                Action::make('reprocess')
                    ->label(fn (WhatsAppImport $record) => $record->status === WhatsAppImportStatus::Processing->value ? 'Unlock & Re-process' : 'Re-process')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (WhatsAppImport $record) =>
                        in_array($record->status, [
                            WhatsAppImportStatus::Completed->value,
                            WhatsAppImportStatus::CompletedWithErrors->value,
                            WhatsAppImportStatus::Failed->value,
                            WhatsAppImportStatus::Processing->value,
                        ])
                        && $record->storage_path
                        && file_exists(storage_path('app/private/' . $record->storage_path))
                    )
                    ->requiresConfirmation()
                    ->modalHeading(fn (WhatsAppImport $record) => $record->status === WhatsAppImportStatus::Processing->value ? 'Unlock stuck import?' : 'Re-process this import?')
                    ->modalDescription(fn (WhatsAppImport $record) => $record->status === WhatsAppImportStatus::Processing->value
                        ? 'This import appears stuck (the job timed out before it could update its status). It will be reset and re-queued from the beginning. Existing records will be updated or counted as duplicates.'
                        : 'The import will be reset to pending and re-queued. Existing records will be updated or counted as duplicates.'
                    )
                    ->action(function (WhatsAppImport $record): void {
                        $record->update([
                            'status'          => WhatsAppImportStatus::Pending->value,
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
                            WhatsAppImportType::CampaignResults->value => ProcessWhatsAppCampaignResultsImport::dispatch($record->id)->onQueue('imports'),
                            WhatsAppImportType::Unsubscribers->value   => ProcessWhatsAppUnsubscriberImport::dispatch($record->id)->onQueue('imports'),
                            default                                     => ProcessWhatsAppRawImport::dispatch($record->id)->onQueue('imports'),
                        };
                        Notification::make()->title('Re-queued — watch status update below')->warning()->send();
                    }),

                DeleteAction::make()
                    ->visible(fn (WhatsAppImport $r) => ! in_array($r->status, ['processing', 'deleting'])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function formatErrors(WhatsAppImport $record): string
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
