<?php

namespace App\Filament\Resources\ImportStagings\Pages;

use App\Filament\Resources\ImportStagings\ImportStagingResource;
use App\Filament\Widgets\RawContactImportProgressWidget;
use App\Models\ImportStaging;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Jobs\PromoteStagingContactsJob;
use App\Modules\IVR\Models\IvrImport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ListImportStagings extends ListRecords
{
    protected static string $resource = ImportStagingResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RawContactImportProgressWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('promote_all')
                ->label('Promote All Needs Review')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->form(function (): array {
                    $batches = ImportStaging::query()
                        ->where('status', ImportStaging::STATUS_NEEDS_REVIEW)
                        ->select('batch_id', DB::raw('count(*) as cnt'))
                        ->groupBy('batch_id')
                        ->orderByDesc('cnt')
                        ->get()
                        ->mapWithKeys(fn ($row) => [
                            $row->batch_id => self::batchLabel($row->batch_id) . " ({$row->cnt} rows)",
                        ])
                        ->all();

                    return [
                        Select::make('batch_id')
                            ->label('Import batch')
                            ->options(array_merge(['__all__' => 'All batches'], $batches))
                            ->default(array_key_first($batches) ?? '__all__')
                            ->required(),
                    ];
                })
                ->action(function (array $data): void {
                    $batchId = $data['batch_id'] === '__all__' ? null : $data['batch_id'];
                    $count   = ImportStaging::where('status', ImportStaging::STATUS_NEEDS_REVIEW)
                        ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
                        ->count();

                    if ($count === 0) {
                        Notification::make()->warning()->title('No rows to promote.')->send();
                        return;
                    }

                    PromoteStagingContactsJob::dispatch($batchId)->onQueue('imports');

                    Notification::make()
                        ->success()
                        ->title("Queued — promoting {$count} staged rows to contacts.")
                        ->body('This runs in the background. Refresh to see rows change to Matched.')
                        ->send();
                })
                ->modalHeading('Promote Staged Rows to Contacts')
                ->modalSubmitActionLabel('Promote')
                ->visible(fn () => ImportStaging::where('status', ImportStaging::STATUS_NEEDS_REVIEW)->exists()),

            Action::make('upload_contacts')
                ->label('Upload Contacts')
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
                        ->placeholder('e.g. Al Reeman 2026')
                        ->maxLength(255),

                    Placeholder::make('format_guide')
                        ->label('Expected CSV columns')
                        ->content(new HtmlString(self::formatGuideHtml()))
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    // Filament FileUpload returns the full relative path from the disk root
                    // (e.g. "ivr/imports/raw/tmp/file.csv"), not just the filename.
                    $tmpPath      = is_array($data['file']) ? ($data['file'][0] ?? '') : ($data['file'] ?? '');
                    $originalName = basename($tmpPath);

                    if (! $originalName || ! $tmpPath) {
                        Notification::make()->title('No file selected.')->danger()->send();
                        return;
                    }

                    $finalPath = 'ivr/imports/raw/'.$originalName;

                    // Block only if a non-failed import with the same filename already exists.
                    // Failed imports can be re-uploaded (new file overwrites the old one on disk).
                    $blocked = IvrImport::query()
                        ->where('type', IvrImportType::RawContacts->value)
                        ->where('original_file_name', $originalName)
                        ->whereNull('reverted_at')
                        ->whereNotIn('status', [
                            IvrImportStatus::Failed->value,
                        ])
                        ->exists();

                    if ($blocked) {
                        Notification::make()
                            ->title("This file has already been imported.")
                            ->body('If you want to re-import, use the Try Again button on the existing import card.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Storage::disk('local')->move($tmpPath, $finalPath);

                    $import = IvrImport::create([
                        'type'               => IvrImportType::RawContacts,
                        'status'             => IvrImportStatus::Pending,
                        'original_file_name' => $originalName,
                        'stored_file_name'   => $originalName,
                        'storage_path'       => $finalPath,
                        'source_name'        => $data['source_name'] ?: null,
                        'uploaded_by'        => auth()->id(),
                    ]);

                    $import->broadcastProgress();
                    ProcessRawIvrImport::dispatch($import->id)->onQueue('imports');

                    Notification::make()
                        ->title('Import queued — watch progress in the table above.')
                        ->body('Name-only rows will appear below as "Needs Review" once the import completes.')
                        ->success()
                        ->send();
                })
                ->modalHeading('Upload Contacts CSV')
                ->modalSubmitActionLabel('Upload & Import')
                ->modalWidth('2xl'),
        ];
    }

    private static function batchLabel(string $batchId): string
    {
        if (preg_match('/^raw-import-(\d+)$/', $batchId, $m)) {
            return 'Import #' . $m[1];
        }

        return substr($batchId, 0, 16) . (strlen($batchId) > 16 ? '…' : '');
    }

    private static function formatGuideHtml(): string
    {
        $required = [
            ['name',  'Contact full name', true],
            ['phone', 'Phone number (any format)', false],
            ['email', 'Email address', false],
        ];

        $optional = [
            ['emirate',              'Dubai / Abu Dhabi / Sharjah …'],
            ['official_area_name',   'DLD area name'],
            ['marketing_area_name',  'Community / marketing area'],
            ['project_name',         'Development or project name'],
            ['building_name',        'Tower or building name'],
            ['unit_reference',       'Unit / apartment number'],
            ['relationship_type',    'owner · tenant · investor · buyer_interest …  (becomes a tag)'],
            ['confidence_level',     'high · medium · low'],
            ['country_iso',          '2-letter ISO code, e.g. AE'],
            ['source',               'Source file label (used if no source name is set above)'],
        ];

        $reqRows = '';
        foreach ($required as [$col, $desc, $req]) {
            $badge = $req
                ? '<span style="color:#16a34a;font-size:10px;font-weight:700">required</span>'
                : '<span style="color:#2563eb;font-size:10px">at least one</span>';
            $reqRows .= "<tr>
                <td style='padding:3px 10px 3px 0;font-family:monospace;font-size:12px;white-space:nowrap;color:#111827'>{$col}</td>
                <td style='padding:3px 10px 3px 0;font-size:12px;color:#374151'>{$desc}</td>
                <td style='padding:3px 0;font-size:11px'>{$badge}</td>
            </tr>";
        }

        $optRows = '';
        foreach ($optional as [$col, $desc]) {
            $optRows .= "<tr>
                <td style='padding:2px 10px 2px 0;font-family:monospace;font-size:12px;white-space:nowrap;color:#111827'>{$col}</td>
                <td style='padding:2px 0;font-size:12px;color:#6b7280'>{$desc}</td>
            </tr>";
        }

        return "
        <div style='font-size:13px;line-height:1.5'>
            <p style='margin-bottom:8px;color:#374151'>
                <strong>name</strong> is always required. You must also provide
                <strong>phone</strong>, <strong>email</strong>, or both.
                Rows with only a name are staged for manual review below.
            </p>

            <table style='border-collapse:collapse;margin-bottom:10px'>
                <thead><tr>
                    <th style='padding:3px 10px 3px 0;text-align:left;font-size:11px;color:#9ca3af;font-weight:600'>Column</th>
                    <th style='padding:3px 10px 3px 0;text-align:left;font-size:11px;color:#9ca3af;font-weight:600'>Description</th>
                    <th></th>
                </tr></thead>
                <tbody>{$reqRows}</tbody>
            </table>

            <p style='margin-bottom:6px;color:#6b7280;font-size:12px;font-weight:600'>Optional columns (any order, column header must match exactly):</p>
            <table style='border-collapse:collapse'>
                <tbody>{$optRows}</tbody>
            </table>

            <p style='margin-top:10px;font-size:12px;color:#6b7280'>
                Column order does not matter. Extra columns are ignored.
                The <strong>relationship_type</strong> column is automatically converted to a tag on each contact.
            </p>
        </div>";
    }
}
