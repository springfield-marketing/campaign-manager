<?php

namespace App\Filament\Resources\IvrImports\Tables;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Jobs\ProcessIvrCampaignResultsImport;
use App\Modules\IVR\Jobs\ProcessUnsubscriberImport;
use App\Modules\IVR\Models\IvrImport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
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
                        'failed', 'delete_failed', 'revert_failed' => 'danger',
                        default                 => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),

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

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('d M H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_column(
                        array_map(fn ($c) => ['value' => $c->value, 'label' => ucwords(str_replace('_', ' ', $c->value))],
                        IvrImportStatus::cases()),
                        'label', 'value'
                    )),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Upload CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->form([
                        FileUpload::make('file')
                            ->label('CSV File')
                            ->required()
                            ->disk('local')
                            ->directory('ivr/imports/raw/tmp')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(51200),

                        TextInput::make('source_name')
                            ->label('Source Name')
                            ->placeholder('e.g. Marina Towers Owners 2025')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $tmpRelative = 'ivr/imports/raw/tmp/' . $data['file'];
                        $originalName = $data['file'];
                        $finalRelative = 'ivr/imports/raw/' . $originalName;

                        if (IvrImport::where('original_file_name', $originalName)->whereNull('reverted_at')->exists()) {
                            Notification::make()
                                ->title("An import named \"{$originalName}\" already exists. Rename the file if this is a new upload.")
                                ->danger()
                                ->send();
                            return;
                        }

                        Storage::disk('local')->move($tmpRelative, $finalRelative);

                        $import = IvrImport::create([
                            'type'               => 'raw_contacts',
                            'status'             => IvrImportStatus::Pending,
                            'original_file_name' => $originalName,
                            'stored_file_name'   => $originalName,
                            'storage_path'       => $finalRelative,
                            'source_name'        => $data['source_name'] ?: null,
                            'uploaded_by'        => auth()->id(),
                        ]);

                        $import->broadcastProgress();
                        ProcessRawIvrImport::dispatch($import->id)->onQueue('imports');

                        Notification::make()
                            ->title('Import queued — status will update automatically')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Upload IVR Raw Import CSV')
                    ->modalSubmitActionLabel('Upload & Queue'),
            ])
            ->recordActions([
                Action::make('reprocess')
                    ->label('Re-process')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (IvrImport $record) => in_array($record->status, ['completed', 'completed_with_errors', 'failed'])
                        && $record->storage_path
                        && file_exists(storage_path('app/private/' . $record->storage_path))
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Re-process this import?')
                    ->modalDescription('The import will be reset to pending and re-queued. Existing contacts from this file will be updated or skipped as duplicates.')
                    ->action(function (IvrImport $record): void {
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
                            'raw_contacts'     => ProcessRawIvrImport::dispatch($record->id)->onQueue('imports'),
                            'campaign_results' => ProcessIvrCampaignResultsImport::dispatch($record->id)->onQueue('imports'),
                            'unsubscribers'    => ProcessUnsubscriberImport::dispatch($record->id)->onQueue('imports'),
                            default            => ProcessRawIvrImport::dispatch($record->id)->onQueue('imports'),
                        };
                        Notification::make()->title('Re-queued — watch status update below')->warning()->send();
                    }),

                DeleteAction::make()
                    ->visible(fn (IvrImport $record) => ! in_array($record->status, ['processing', 'deleting'])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
