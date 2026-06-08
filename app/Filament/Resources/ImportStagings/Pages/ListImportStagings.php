<?php

namespace App\Filament\Resources\ImportStagings\Pages;

use App\Filament\Resources\ImportStagings\ImportStagingResource;
use App\Models\Tag;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListImportStagings extends ListRecords
{
    protected static string $resource = ImportStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload_ivr_contacts')
                ->label('Upload IVR Contacts')
                ->icon('heroicon-o-phone')
                ->color('primary')
                ->form([
                    FileUpload::make('file')
                        ->label('CSV File')
                        ->required()
                        ->disk('local')
                        ->directory('ivr/imports/raw/tmp')
                        ->preserveFilenames()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->maxSize(51200)
                        ->helperText('Required: name + phone, or name + email. Name-only rows are staged for review.'),

                    TextInput::make('source_name')
                        ->label('Source Name')
                        ->placeholder('e.g. Al Reeman 2026')
                        ->maxLength(255),

                    TextInput::make('tag_name')
                        ->label('Tag (optional)')
                        ->placeholder('e.g. Owner')
                        ->helperText('Every contact in this file will receive this tag. Creates the tag if it does not exist.')
                        ->maxLength(100)
                        ->datalist(fn () => Tag::orderBy('name')->pluck('name')->all()),
                ])
                ->action(function (array $data): void {
                    $originalName = $data['file'];
                    $tmpPath      = 'ivr/imports/raw/tmp/'.$originalName;
                    $finalPath    = 'ivr/imports/raw/'.$originalName;

                    if (IvrImport::query()
                        ->where('type', IvrImportType::RawContacts->value)
                        ->where('original_file_name', $originalName)
                        ->whereNull('reverted_at')
                        ->exists()
                    ) {
                        Notification::make()
                            ->title("An import named \"{$originalName}\" already exists.")
                            ->body('Rename the file if this is a new upload.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Storage::disk('local')->move($tmpPath, $finalPath);

                    $tagId = null;
                    if (! empty($data['tag_name'])) {
                        $tagId = Tag::firstOrCreate(['name' => trim($data['tag_name'])])->id;
                    }

                    $import = IvrImport::create([
                        'type'               => IvrImportType::RawContacts,
                        'status'             => IvrImportStatus::Pending,
                        'original_file_name' => $originalName,
                        'stored_file_name'   => $originalName,
                        'storage_path'       => $finalPath,
                        'source_name'        => $data['source_name'] ?: null,
                        'uploaded_by'        => auth()->id(),
                        'tag_id'             => $tagId,
                    ]);

                    $import->broadcastProgress();
                    ProcessRawIvrImport::dispatch($import->id)->onQueue('imports');

                    Notification::make()
                        ->title('IVR contacts import queued — name-only rows will appear in this table as "Needs Review".')
                        ->success()
                        ->send();
                })
                ->modalHeading('Upload IVR Raw Contacts CSV')
                ->modalDescription('Accepted columns: name, phone, email, emirate, official_area_name, marketing_area_name, project_name, building_name, unit_reference, relationship_type, confidence_level, source.')
                ->modalSubmitActionLabel('Upload & Queue'),

            Action::make('upload_whatsapp_contacts')
                ->label('Upload WhatsApp Contacts')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->form([
                    FileUpload::make('file')
                        ->label('CSV File')
                        ->required()
                        ->disk('local')
                        ->directory('ivr/imports/raw/tmp')
                        ->preserveFilenames()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->maxSize(51200)
                        ->helperText('Same format as IVR contacts. Name-only rows are staged for review.'),

                    TextInput::make('source_name')
                        ->label('Source Name')
                        ->placeholder('e.g. WhatsApp Broadcast June 2026')
                        ->maxLength(255),

                    TextInput::make('tag_name')
                        ->label('Tag (optional)')
                        ->placeholder('e.g. WhatsApp Lead')
                        ->helperText('Every contact in this file will receive this tag.')
                        ->maxLength(100)
                        ->datalist(fn () => Tag::orderBy('name')->pluck('name')->all()),
                ])
                ->action(function (array $data): void {
                    $originalName = $data['file'];
                    $tmpPath      = 'ivr/imports/raw/tmp/'.$originalName;
                    $finalPath    = 'ivr/imports/raw/'.$originalName;

                    if (Storage::disk('local')->exists($finalPath)) {
                        Notification::make()
                            ->title("A file named \"{$originalName}\" already exists.")
                            ->body('Rename the file if this is a new upload.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Storage::disk('local')->move($tmpPath, $finalPath);

                    $tagId = null;
                    if (! empty($data['tag_name'])) {
                        $tagId = Tag::firstOrCreate(['name' => trim($data['tag_name'])])->id;
                    }

                    $import = IvrImport::create([
                        'type'               => IvrImportType::RawContacts,
                        'status'             => IvrImportStatus::Pending,
                        'original_file_name' => $originalName,
                        'stored_file_name'   => $originalName,
                        'storage_path'       => $finalPath,
                        'source_name'        => $data['source_name'] ?: null,
                        'uploaded_by'        => auth()->id(),
                        'tag_id'             => $tagId,
                    ]);

                    $import->broadcastProgress();
                    ProcessRawIvrImport::dispatch($import->id)->onQueue('imports');

                    Notification::make()
                        ->title('WhatsApp contacts import queued — name-only rows will appear in this table as "Needs Review".')
                        ->success()
                        ->send();
                })
                ->modalHeading('Upload WhatsApp Contacts CSV')
                ->modalDescription('Same format as IVR contacts. Use the tag field to label these as WhatsApp leads or owners.')
                ->modalSubmitActionLabel('Upload & Queue'),
        ];
    }
}
