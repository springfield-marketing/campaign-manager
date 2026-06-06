<?php

namespace App\Filament\Resources\IvrImports\Tables;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Jobs\ProcessIvrCampaignResultsImport;
use App\Modules\IVR\Jobs\ProcessUnsubscriberImport;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Models\IvrScript;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class IvrImportsTable
{
    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'raw_contacts'     => 'Raw Contacts',
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
                        'raw_contacts'     => 'primary',
                        'campaign_results' => 'success',
                        'unsubscribers'    => 'warning',
                        default            => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => self::typeLabel($state)),

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

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'raw_contacts'     => 'Raw Contacts',
                        'campaign_results' => 'Campaign Results',
                        'unsubscribers'    => 'Unsubscribers',
                    ]),

                SelectFilter::make('status')
                    ->options(array_column(
                        array_map(fn ($c) => ['value' => $c->value, 'label' => ucwords(str_replace('_', ' ', $c->value))],
                        IvrImportStatus::cases()),
                        'label', 'value'
                    )),
            ])
            ->headerActions([
                // ── Raw contacts upload ────────────────────────────────────
                Action::make('upload_raw')
                    ->label('Upload Raw Contacts')
                    ->icon('heroicon-o-user-group')
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
                        $originalName = $data['file'];
                        $tmpRelative  = 'ivr/imports/raw/tmp/' . $originalName;
                        $finalRelative = 'ivr/imports/raw/' . $originalName;

                        if (IvrImport::where('original_file_name', $originalName)->whereNull('reverted_at')->exists()) {
                            Notification::make()
                                ->title("An import named \"{$originalName}\" already exists. Rename the file if this is a new upload.")
                                ->danger()->send();
                            return;
                        }

                        Storage::disk('local')->move($tmpRelative, $finalRelative);

                        $import = IvrImport::create([
                            'type'               => IvrImportType::RawContacts,
                            'status'             => IvrImportStatus::Pending,
                            'original_file_name' => $originalName,
                            'stored_file_name'   => $originalName,
                            'storage_path'       => $finalRelative,
                            'source_name'        => $data['source_name'] ?: null,
                            'uploaded_by'        => auth()->id(),
                        ]);

                        $import->broadcastProgress();
                        ProcessRawIvrImport::dispatch($import->id)->onQueue('imports');

                        Notification::make()->title('Import queued — status will update automatically')->success()->send();
                    })
                    ->modalHeading('Upload Raw Contacts CSV')
                    ->modalDescription('Required columns: name, phone. Optional: email, nationality, city, community, interest, source.')
                    ->modalSubmitActionLabel('Upload & Queue'),

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
                        $originalName  = $data['file'];
                        $tmpRelative   = 'ivr/imports/results/tmp/' . $originalName;
                        $finalRelative = 'ivr/imports/results/' . $originalName;

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
                        ProcessIvrCampaignResultsImport::dispatch($import->id)->onQueue('imports');

                        Notification::make()->title('Campaign results import queued — status will update automatically')->success()->send();
                    })
                    ->modalHeading('Upload Campaign Results CSV')
                    ->modalDescription('Upload the campaign results CSV exported from your IVR platform. Optionally link a script for reference.')
                    ->modalSubmitActionLabel('Upload & Queue'),
            ])
            ->recordActions([
                Action::make('reprocess')
                    ->label('Re-process')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (IvrImport $record) =>
                        in_array($record->status, ['completed', 'completed_with_errors', 'failed'])
                        && $record->storage_path
                        && file_exists(storage_path('app/private/' . $record->storage_path))
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Re-process this import?')
                    ->modalDescription('The import will be reset to pending and re-queued. Existing records will be updated or counted as duplicates.')
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
