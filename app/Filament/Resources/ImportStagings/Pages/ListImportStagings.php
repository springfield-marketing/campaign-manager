<?php

namespace App\Filament\Resources\ImportStagings\Pages;

use App\Filament\Resources\ImportStagings\ImportStagingResource;
use App\Models\Tag;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\RawImportColumnMapper;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use SplFileObject;
use Throwable;

class ListImportStagings extends ListRecords
{
    protected static string $resource = ImportStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                        ->maxSize(51200)
                        ->live()
                        ->helperText('Required: name + phone, or name + email. Name-only rows are staged for review.'),

                    TextInput::make('source_name')
                        ->label('Source Name')
                        ->placeholder('e.g. Al Reeman 2026')
                        ->maxLength(255),

                    TextInput::make('tag_name')
                        ->label('Tag (optional)')
                        ->placeholder('e.g. Owner')
                        ->helperText('Every contact in this file will receive this tag. Creates the tag if it does not exist.')
                        ->maxLength(100),

                    Placeholder::make('preview')
                        ->label('File Preview')
                        ->content(fn (Get $get) => self::buildPreview($get('file')))
                        ->visible(fn (Get $get) => (bool) $get('file'))
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $originalName = is_array($data['file']) ? ($data['file'][0] ?? null) : $data['file'];

                    if (! $originalName) {
                        Notification::make()->title('No file selected.')->danger()->send();
                        return;
                    }

                    $tmpPath   = 'ivr/imports/raw/tmp/'.$originalName;
                    $finalPath = 'ivr/imports/raw/'.$originalName;

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
                        ->title('Import queued.')
                        ->body('Name-only rows will appear in this table as "Needs Review".')
                        ->success()
                        ->send();
                })
                ->modalHeading('Upload Contacts CSV')
                ->modalDescription('Upload a contacts CSV. A preview of your data will appear once the file is selected — review it before confirming.')
                ->modalSubmitActionLabel('Confirm & Import')
                ->modalWidth('5xl'),
        ];
    }

    private static function buildPreview(mixed $filename): HtmlString
    {
        if (! $filename) {
            return new HtmlString('');
        }

        $filename = is_array($filename) ? ($filename[0] ?? null) : $filename;

        if (! $filename) {
            return new HtmlString('');
        }

        $path = storage_path('app/private/ivr/imports/raw/tmp/'.basename($filename));

        if (! file_exists($path)) {
            return new HtmlString('<p class="text-sm text-gray-500 italic">Waiting for file to finish uploading…</p>');
        }

        try {
            $file = new SplFileObject($path);
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');

            // Read header
            $header = null;
            while (! $file->eof() && $header === null) {
                $row = $file->fgetcsv();
                if (is_array($row) && array_filter($row) !== []) {
                    $row     = array_map(fn ($v) => trim((string) $v), $row);
                    $row[0]  = ltrim($row[0], "\xEF\xBB\xBF");
                    $header  = $row;
                }
            }

            if (! $header) {
                return new HtmlString('<p class="text-sm text-red-500">Could not read a header row from this file.</p>');
            }

            // Map columns using existing alias config
            $mapper  = app(RawImportColumnMapper::class);
            $mapping = $mapper->map($header);
            $colMap  = $mapping['mapped'];   // ['name' => 0, 'phone' => 2, ...]
            $reverse = array_flip($colMap);  // [0 => 'name', 2 => 'phone', ...]
            $missing = $mapping['missing'];  // required columns not found

            // Scan the full file for stats, collect preview rows
            $previewRows = [];
            $total       = 0;
            $withPhone   = 0;
            $withEmail   = 0;
            $nameOnly    = 0;
            $phoneIdx    = $colMap['phone'] ?? null;
            $emailIdx    = $colMap['email'] ?? null;

            while (! $file->eof()) {
                $row = $file->fgetcsv();
                if (! is_array($row) || ($row === [null] && $file->eof())) break;
                if (array_filter($row) === []) continue;

                $total++;
                $hasPhone = $phoneIdx !== null && trim($row[$phoneIdx] ?? '') !== '';
                $hasEmail = $emailIdx !== null && trim($row[$emailIdx] ?? '') !== '';

                if ($hasPhone) $withPhone++;
                elseif ($hasEmail) $withEmail++;
                else $nameOnly++;

                if (count($previewRows) < 12) {
                    $previewRows[] = $row;
                }
            }

            // ── Column-mapping header ──────────────────────────────────────
            $thCells = '';
            foreach ($header as $i => $col) {
                $canonical = $reverse[$i] ?? null;
                if ($canonical) {
                    $isRequired = in_array($canonical, config('ivr.raw_import.required', ['name']), true);
                    $badge = $isRequired
                        ? '<span style="color:#16a34a;font-size:10px">✓ '.$canonical.'</span>'
                        : '<span style="color:#2563eb;font-size:10px">→ '.$canonical.'</span>';
                    $thCells .= '<th style="padding:4px 8px;text-align:left;border-bottom:1px solid #e5e7eb;white-space:nowrap;font-weight:600">'.e($col).'<br>'.$badge.'</th>';
                } else {
                    $thCells .= '<th style="padding:4px 8px;text-align:left;border-bottom:1px solid #e5e7eb;white-space:nowrap;color:#9ca3af;font-weight:400">'.e($col).'<br><span style="font-size:10px">ignored</span></th>';
                }
            }

            // ── Preview rows ───────────────────────────────────────────────
            $trRows = '';
            foreach ($previewRows as $row) {
                $tds = '';
                foreach ($header as $i => $_) {
                    $val  = trim($row[$i] ?? '');
                    $tds .= '<td style="padding:3px 8px;border-bottom:1px solid #f3f4f6;font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'.e($val !== '' ? $val : '—').'</td>';
                }
                $trRows .= '<tr>'.$tds.'</tr>';
            }

            if ($total > 12) {
                $span    = count($header);
                $trRows .= '<tr><td colspan="'.$span.'" style="padding:6px 8px;font-size:11px;color:#9ca3af;text-align:center">… and '.number_format($total - 12).' more rows</td></tr>';
            }

            // ── Stats bar ──────────────────────────────────────────────────
            $namePct   = $total > 0 ? round($withPhone / $total * 100) : 0;
            $emailPct  = $total > 0 ? round($withEmail / $total * 100) : 0;
            $stagePct  = $total > 0 ? round($nameOnly / $total * 100) : 0;

            $statsHtml = '
            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:10px;font-size:13px">
                <span><strong>'.number_format($total).'</strong> total rows</span>
                <span style="color:#16a34a"><strong>'.number_format($withPhone).'</strong> with phone ('.$namePct.'%)</span>
                <span style="color:#2563eb"><strong>'.number_format($withEmail).'</strong> email-only ('.$emailPct.'%)</span>
                '.($nameOnly > 0
                    ? '<span style="color:#d97706"><strong>'.number_format($nameOnly).'</strong> name-only → staged for review ('.$stagePct.'%)</span>'
                    : '').'
            </div>';

            $warningHtml = '';
            if (! empty($missing)) {
                $warningHtml = '<p style="margin-top:8px;font-size:13px;color:#dc2626">⚠ Missing required columns: '.e(implode(', ', $missing)).'. Import will fail.</p>';
            }

            $html = '
            <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:6px;margin-top:4px">
                <table style="min-width:100%;border-collapse:collapse;font-size:12px">
                    <thead style="background:#f9fafb"><tr>'.$thCells.'</tr></thead>
                    <tbody>'.$trRows.'</tbody>
                </table>
            </div>
            '.$statsHtml.$warningHtml;

            return new HtmlString($html);

        } catch (Throwable $e) {
            return new HtmlString('<p style="font-size:13px;color:#dc2626">Could not parse file: '.e($e->getMessage()).'</p>');
        }
    }
}
