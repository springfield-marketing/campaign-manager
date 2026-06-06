<?php

namespace App\Filament\Resources\WhatsAppImports\Tables;

use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppRawImport;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppRawImportColumnMapper;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Storage;

class WhatsAppImportsTable
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
                        'draft'                 => 'gray',
                        'failed', 'delete_failed', 'revert_failed' => 'danger',
                        default                 => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),

                TextColumn::make('total_rows')->label('Total')->numeric()->sortable(),
                TextColumn::make('successful_rows')->label('OK')->numeric()->color('success'),
                TextColumn::make('failed_rows')->label('Failed')->numeric()
                    ->color(fn (?int $state) => ($state ?? 0) > 0 ? 'danger' : 'gray'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
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
                Action::make('upload')
                    ->label('Upload CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->form([
                        FileUpload::make('file')
                            ->label('CSV File')
                            ->required()
                            ->disk('local')
                            ->directory('whatsapp/imports/raw/tmp')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->maxSize(51200),

                        TextInput::make('source_name')
                            ->label('Source Name')
                            ->placeholder('e.g. Saadiyat Owners Jun 2025')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $originalName  = $data['file'];
                        $tmpRelative   = 'whatsapp/imports/raw/tmp/' . $originalName;
                        $finalRelative = 'whatsapp/imports/raw/' . $originalName;

                        Storage::disk('local')->move($tmpRelative, $finalRelative);

                        // Auto-detect column mapping
                        $fullPath = Storage::disk('local')->path($finalRelative);
                        $handle   = fopen($fullPath, 'r');
                        $headers  = $handle ? (fgetcsv($handle) ?: []) : [];
                        if ($handle) {
                            fclose($handle);
                        }

                        $mapper  = app(WhatsAppRawImportColumnMapper::class);
                        $mapping = $mapper->map($headers);

                        $status = empty($mapping['missing'])
                            ? WhatsAppImportStatus::Pending
                            : WhatsAppImportStatus::Draft;

                        $import = WhatsAppImport::create([
                            'type'                => 'raw_contacts',
                            'status'              => $status,
                            'original_file_name'  => $originalName,
                            'stored_file_name'    => $originalName,
                            'storage_path'        => $finalRelative,
                            'source_name'         => $data['source_name'] ?: null,
                            'uploaded_by'         => auth()->id(),
                            'column_mapping'      => $mapping['mapped'],
                        ]);

                        if ($status === WhatsAppImportStatus::Pending) {
                            ProcessWhatsAppRawImport::dispatch($import->id)->onQueue('imports');
                            Notification::make()->title('Import queued — status updates automatically')->success()->send();
                        } else {
                            Notification::make()
                                ->title('Column mapping needed')
                                ->body('Could not auto-detect: ' . implode(', ', $mapping['missing']) . '. Use "Map Columns" to fix.')
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->modalHeading('Upload WhatsApp Raw Import CSV')
                    ->modalSubmitActionLabel('Upload'),
            ])
            ->recordActions([
                Action::make('map_columns')
                    ->label('Map Columns')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->visible(fn (WhatsAppImport $r) => $r->status === 'draft')
                    ->fillForm(fn (WhatsAppImport $r) => self::buildMappingFormData($r))
                    ->form(fn (WhatsAppImport $r) => self::buildMappingForm($r))
                    ->action(function (WhatsAppImport $record, array $data): void {
                        $mapping = array_filter(
                            $data['mapping'] ?? [],
                            fn ($v) => $v !== null && $v !== ''
                        );

                        $record->update([
                            'column_mapping' => $mapping,
                            'status'         => WhatsAppImportStatus::Pending,
                        ]);

                        ProcessWhatsAppRawImport::dispatch($record->id)->onQueue('imports');

                        Notification::make()->title('Mapping saved — import queued')->success()->send();
                    })
                    ->modalHeading('Map CSV Columns')
                    ->modalSubmitActionLabel('Save & Queue'),

                DeleteAction::make()
                    ->visible(fn (WhatsAppImport $r) => ! in_array($r->status, ['processing', 'deleting'])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function buildMappingFormData(WhatsAppImport $import): array
    {
        // Pre-fill from existing mapping
        $existing = $import->column_mapping ?? [];
        $result   = [];
        foreach ($existing as $canonical => $index) {
            $result["mapping.{$canonical}"] = (string) $index;
        }
        return $result;
    }

    private static function buildMappingForm(WhatsAppImport $import): array
    {
        // Read headers from stored file
        $fullPath = Storage::disk('local')->path($import->storage_path);
        $handle   = fopen($fullPath, 'r');
        $headers  = $handle ? (fgetcsv($handle) ?: []) : [];
        if ($handle) {
            fclose($handle);
        }

        $headerOptions = ['__skip__' => '— skip —'];
        foreach ($headers as $i => $h) {
            $headerOptions[(string) $i] = "Col {$i}: {$h}";
        }

        $canonicals = array_keys(config('whatsapp.raw_import.aliases', []));
        $required   = config('whatsapp.raw_import.required', []);

        $fields = [];
        foreach ($canonicals as $field) {
            $fields[] = Select::make("mapping.{$field}")
                ->label(ucwords(str_replace('_', ' ', $field)) . (in_array($field, $required) ? ' *' : ''))
                ->options($headerOptions)
                ->required(in_array($field, $required))
                ->nullable(!in_array($field, $required));
        }

        return $fields;
    }
}

// Ensure Select is imported