<?php

namespace App\Filament\Resources\IvrImports\Tables;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
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
                DeleteAction::make()
                    ->visible(fn (IvrImport $record) => ! in_array($record->status, ['processing', 'deleting'])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
