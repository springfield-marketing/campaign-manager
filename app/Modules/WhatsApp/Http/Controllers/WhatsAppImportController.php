<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppImportType;
use App\Modules\WhatsApp\Jobs\DeleteWhatsAppRawImport;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppCampaignResultsImport;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppRawImport;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppImportStatusPayload;
use App\Modules\WhatsApp\Support\WhatsAppRawImportColumnMapper;
use App\Modules\WhatsApp\Support\WhatsAppRawImportDeleter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppImportController extends Controller
{
    // -----------------------------------------------------------------------
    // History list
    // -----------------------------------------------------------------------

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString() ?: 'contacts';

        return view('whatsapp::imports.index', [
            'tab' => $tab,
            'imports' => WhatsAppImport::query()
                ->where('type', WhatsAppImportType::RawContacts)
                ->latest()
                ->paginate(15),
            'campaignImports' => WhatsAppImport::query()
                ->where('type', WhatsAppImportType::CampaignResults)
                ->latest()
                ->paginate(15, ['*'], 'campaign_page'),
        ]);
    }

    public function show(WhatsAppImport $import): View
    {
        $import->load(['errors' => fn ($q) => $q->orderBy('row_number')->limit(500)]);

        return view('whatsapp::imports.show', compact('import'));
    }

    // -----------------------------------------------------------------------
    // Wizard — Step 1: upload file
    // -----------------------------------------------------------------------

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file'        => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'source_name' => ['nullable', 'string', 'max:255'],
        ], [
            'file.uploaded' => 'The file could not be uploaded. Check PHP upload limits.',
            'file.max'      => 'The file must be 50 MB or smaller.',
        ]);

        $originalFileName = $validated['file']->getClientOriginalName();

        $existing = WhatsAppImport::query()
            ->where('type', WhatsAppImportType::RawContacts)
            ->where('original_file_name', $originalFileName)
            ->whereNull('reverted_at')
            ->where('status', '!=', WhatsAppImportStatus::Draft->value)
            ->exists();

        if ($existing) {
            return back()
                ->withErrors(['file' => "A raw import named {$originalFileName} already exists. Rename the file if this is a new upload."])
                ->withInput();
        }

        $storedPath = $validated['file']->store('whatsapp/imports/raw', 'local');

        // Read headers and auto-detect mapping before the user sees the map step.
        $file   = $this->openCsv($storedPath);
        $header = $file->fgetcsv() ?: [];

        $detected = app(WhatsAppRawImportColumnMapper::class)->map($header);

        $import = WhatsAppImport::create([
            'type'               => WhatsAppImportType::RawContacts,
            'status'             => WhatsAppImportStatus::Draft,
            'original_file_name' => $originalFileName,
            'stored_file_name'   => basename($storedPath),
            'storage_path'       => $storedPath,
            'source_name'        => $validated['source_name'] ?: null,
            'uploaded_by'        => $request->user()?->id,
            'column_mapping'     => $detected['mapped'],
        ]);

        return redirect()->route('modules.whatsapp.imports.map', $import);
    }

    // -----------------------------------------------------------------------
    // Wizard — Step 2: map columns
    // -----------------------------------------------------------------------

    public function map(WhatsAppImport $import): View
    {
        $this->requireDraft($import);

        $file   = $this->openCsv($import->storage_path);
        $header = $file->fgetcsv() ?: [];

        $samples = [];
        for ($i = 0; $i < 3 && ! $file->eof(); $i++) {
            $row = $file->fgetcsv();
            if (is_array($row) && ! $this->rowIsEmpty($row)) {
                $samples[] = $row;
            }
        }

        $currentMapping = $import->column_mapping ?? [];

        $columns = [];
        foreach ($header as $index => $headerName) {
            $columns[] = [
                'index'     => $index,
                'header'    => $headerName,
                'samples'   => array_map(fn ($r) => $r[$index] ?? '', $samples),
                'mapped_to' => array_search($index, $currentMapping, true) ?: '',
            ];
        }

        return view('whatsapp::imports.map', [
            'import'       => $import,
            'columns'      => $columns,
            'systemFields' => $this->systemFields(),
            'required'     => config('whatsapp.raw_import.required', ['name', 'phone']),
        ]);
    }

    public function mapStore(Request $request, WhatsAppImport $import): RedirectResponse
    {
        $this->requireDraft($import);

        $validated = $request->validate([
            'mapping'   => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
        ]);

        // Build ['name' => 0, 'phone' => 2, ...] skipping empty selections.
        $columnMapping = [];
        foreach ($validated['mapping'] as $colIndex => $field) {
            if ($field !== '' && $field !== null) {
                $columnMapping[$field] = (int) $colIndex;
            }
        }

        // Ensure required fields are mapped.
        $required = config('whatsapp.raw_import.required', ['name', 'phone']);
        $missing  = array_filter($required, fn ($f) => ! array_key_exists($f, $columnMapping));

        if ($missing !== []) {
            return back()->withErrors(['mapping' => 'These required fields are not mapped: '.implode(', ', $missing).'.']);
        }

        // Prevent the same system field being assigned to two different columns.
        $fields = array_values($columnMapping);
        if (count($fields) !== count(array_unique($fields))) {
            return back()->withErrors(['mapping' => 'The same system field cannot be assigned to more than one column.']);
        }

        $import->update(['column_mapping' => $columnMapping]);

        return redirect()->route('modules.whatsapp.imports.preview', $import);
    }

    // -----------------------------------------------------------------------
    // Wizard — Step 3: preview
    // -----------------------------------------------------------------------

    public function preview(WhatsAppImport $import): View
    {
        $this->requireDraft($import);

        $file   = $this->openCsv($import->storage_path);
        $file->fgetcsv(); // skip header row

        $previewRows = [];
        $totalRows   = 0;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }
            $totalRows++;
            if (count($previewRows) < 10) {
                $previewRows[] = $row;
            }
        }

        $mapping      = $import->column_mapping ?? [];
        $systemFields = $this->systemFields();

        // Build rows keyed by system field name for easy rendering.
        $mappedRows = array_map(function (array $row) use ($mapping): array {
            $out = [];
            foreach ($mapping as $field => $colIndex) {
                $out[$field] = $row[$colIndex] ?? '';
            }

            return $out;
        }, $previewRows);

        return view('whatsapp::imports.preview', [
            'import'       => $import,
            'mappedRows'   => $mappedRows,
            'totalRows'    => $totalRows,
            'mapping'      => $mapping,
            'systemFields' => $systemFields,
            'required'     => config('whatsapp.raw_import.required', ['name', 'phone']),
        ]);
    }

    // -----------------------------------------------------------------------
    // Wizard — Step 3b: download preview as CSV
    // -----------------------------------------------------------------------

    public function previewDownload(WhatsAppImport $import): StreamedResponse
    {
        $this->requireDraft($import);

        $file   = $this->openCsv($import->storage_path);
        $file->fgetcsv(); // skip header row

        $mapping      = $import->column_mapping ?? [];
        $systemFields = $this->systemFields();

        $previewRows = [];
        while (! $file->eof() && count($previewRows) < 10) {
            $row = $file->fgetcsv();
            if (is_array($row) && ! $this->rowIsEmpty($row)) {
                $mapped = [];
                foreach ($mapping as $field => $colIndex) {
                    $mapped[$field] = $row[$colIndex] ?? '';
                }
                $previewRows[] = $mapped;
            }
        }

        $headers = array_map(fn ($f) => $systemFields[$f] ?? $f, array_keys($mapping));

        return response()->streamDownload(function () use ($headers, $previewRows, $mapping): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($previewRows as $row) {
                fputcsv($handle, array_map(fn ($f) => $row[$f] ?? '', array_keys($mapping)));
            }
            fclose($handle);
        }, 'preview-' . $import->stored_file_name, ['Content-Type' => 'text/csv']);
    }

    // -----------------------------------------------------------------------
    // Wizard — Step 4: confirm → queue job
    // -----------------------------------------------------------------------

    public function confirm(WhatsAppImport $import): RedirectResponse
    {
        $this->requireDraft($import);

        // Re-check for a duplicate that may have been confirmed while the wizard was open.
        $duplicate = WhatsAppImport::query()
            ->where('type', WhatsAppImportType::RawContacts)
            ->where('original_file_name', $import->original_file_name)
            ->whereNull('reverted_at')
            ->where('id', '!=', $import->id)
            ->where('status', '!=', WhatsAppImportStatus::Draft->value)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['file' => "A raw import named {$import->original_file_name} was confirmed by someone else while you were setting up. Please delete this draft and re-upload with a different filename."]);
        }

        $import->update(['status' => WhatsAppImportStatus::Pending]);
        $import->broadcastProgress();

        ProcessWhatsAppRawImport::dispatch($import->id);

        return redirect()
            ->route('modules.whatsapp.imports.index')
            ->with('status', 'Raw contact import queued successfully.');
    }

    // -----------------------------------------------------------------------
    // Delete / safe-delete
    // -----------------------------------------------------------------------

    public function destroy(Request $request, WhatsAppImport $import): RedirectResponse
    {
        if ($import->type !== WhatsAppImportType::RawContacts->value) {
            abort(404);
        }

        // Drafts have no imported contacts — just delete the file and record.
        if ($import->status === WhatsAppImportStatus::Draft->value) {
            Storage::disk('local')->delete($import->storage_path);
            $import->delete();

            return redirect()
                ->route('modules.whatsapp.imports.index')
                ->with('status', 'Draft import cancelled.');
        }

        if (in_array($import->status, [
            WhatsAppImportStatus::Pending->value,
            WhatsAppImportStatus::Processing->value,
            WhatsAppImportStatus::Deleting->value,
        ], true)) {
            return back()->with('status', 'This import is still running and cannot be deleted yet.');
        }

        if ($import->reverted_at !== null) {
            return back()->with('status', 'This import has already been deleted.');
        }

        $validated = $request->validate([
            'delete_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $import->forceFill([
            'status'        => WhatsAppImportStatus::Deleting,
            'error_message' => null,
            'summary'       => array_merge($import->summary ?? [], [
                'delete_progress' => [
                    'stage'                 => 'queued',
                    'stage_label'           => 'Delete queued',
                    'processed'             => 0,
                    'total'                 => WhatsAppRawImportDeleter::DELETE_STEPS,
                    'percent'               => 0,
                    'source_rows_deleted'   => 0,
                    'phone_numbers_deleted' => 0,
                    'clients_deleted'       => 0,
                ],
            ]),
        ])->save();

        DeleteWhatsAppRawImport::dispatch(
            $import->id,
            $request->user()?->id,
            $validated['delete_reason'] ?? null,
        );

        return redirect()
            ->route('modules.whatsapp.imports.index')
            ->with('status', "Import {$import->original_file_name} is being deleted. The status will update automatically.");
    }

    // -----------------------------------------------------------------------
    // Status polling endpoint
    // -----------------------------------------------------------------------

    public function status(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->take(50)
            ->values();

        $imports = WhatsAppImport::query()
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (WhatsAppImport $import): array => WhatsAppImportStatusPayload::make($import))
            ->values();

        return response()->json(['imports' => $imports]);
    }

    // -----------------------------------------------------------------------
    // Campaign results import — called from the campaigns page form
    // -----------------------------------------------------------------------

    public function storeCampaignResults(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ], [
            'file.uploaded' => 'The file could not be uploaded. Check PHP upload limits.',
            'file.max'      => 'The file must be 50 MB or smaller.',
        ]);

        $originalFileName = $validated['file']->getClientOriginalName();

        $existing = WhatsAppImport::query()
            ->where('type', WhatsAppImportType::CampaignResults)
            ->where('original_file_name', $originalFileName)
            ->whereNull('reverted_at')
            ->exists();

        if ($existing) {
            return back()
                ->withErrors(['file' => "An import named {$originalFileName} already exists."])
                ->withInput();
        }

        $storedPath = $validated['file']->store('whatsapp/imports/campaign-results', 'local');

        $import = WhatsAppImport::create([
            'type'               => WhatsAppImportType::CampaignResults,
            'status'             => WhatsAppImportStatus::Pending,
            'original_file_name' => $originalFileName,
            'stored_file_name'   => basename($storedPath),
            'storage_path'       => $storedPath,
            'uploaded_by'        => $request->user()?->id,
        ]);

        $import->broadcastProgress();

        ProcessWhatsAppCampaignResultsImport::dispatch($import->id);

        return redirect()
            ->route('modules.whatsapp.campaigns.index')
            ->with('status', 'Campaign results import queued successfully.');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function requireDraft(WhatsAppImport $import): void
    {
        if ($import->type !== WhatsAppImportType::RawContacts->value
            || $import->status !== WhatsAppImportStatus::Draft->value) {
            abort(404);
        }
    }

    private function openCsv(string $storagePath): SplFileObject
    {
        $file = new SplFileObject(storage_path('app/private/'.$storagePath));
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '\\');

        return $file;
    }

    /** @param array<int, mixed> $row */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, string> */
    private function systemFields(): array
    {
        return [
            'name'        => 'Name',
            'phone'       => 'Phone',
            'email'       => 'Email',
            'country'     => 'Country',
            'nationality' => 'Nationality',
            'community'   => 'Community',
            'resident'    => 'Resident',
            'city'        => 'City',
            'gender'      => 'Gender',
            'interest'    => 'Interest',
            'source_file' => 'Source',
        ];
    }
}
