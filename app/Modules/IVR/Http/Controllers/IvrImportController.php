<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\DeleteRawIvrImport;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\IvrImportStatusPayload;
use App\Modules\IVR\Support\RawImportDeleter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class IvrImportController extends Controller
{
    public function index(): View
    {
        return view('ivr::imports.index', [
            'imports' => IvrImport::query()
                ->where('type', IvrImportType::RawContacts)
                ->latest()
                ->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
                'source_name' => ['nullable', 'string', 'max:255'],
            ],
            [
                'file.uploaded' => 'The file could not be uploaded because it is larger than the current PHP upload limit. Increase upload_max_filesize and post_max_size, then try again.',
                'file.max' => 'The file must be 50 MB or smaller.',
            ],
        );

        $originalFileName = $validated['file']->getClientOriginalName();

        $existingImport = IvrImport::query()
            ->where('type', IvrImportType::RawContacts)
            ->where('original_file_name', $originalFileName)
            ->whereNull('reverted_at')
            ->exists();

        if ($existingImport) {
            return back()
                ->withErrors(['file' => "A raw import named {$originalFileName} already exists. Rename the file if this is intentionally a new upload."])
                ->withInput();
        }

        $storedPath = $validated['file']->store('ivr/imports/raw', 'local');

        $import = IvrImport::create([
            'type' => IvrImportType::RawContacts,
            'status' => IvrImportStatus::Pending,
            'original_file_name' => $originalFileName,
            'stored_file_name' => basename($storedPath),
            'storage_path' => $storedPath,
            'source_name' => $validated['source_name'] ?: null,
            'uploaded_by' => $request->user()?->id,
        ]);

        $import->broadcastProgress();

        ProcessRawIvrImport::dispatch($import->id);

        return redirect()
            ->route('modules.ivr.imports.index')
            ->with('status', 'Raw import queued successfully.');
    }

    public function show(IvrImport $import): View
    {
        return view('ivr::imports.show', [
            'import' => $import->load(['errors' => fn ($query) => $query->latest()->limit(100)]),
        ]);
    }

    public function destroy(Request $request, IvrImport $import): RedirectResponse
    {
        if ($import->type !== IvrImportType::RawContacts->value) {
            abort(404);
        }

        if (in_array($import->status, [
            IvrImportStatus::Pending->value,
            IvrImportStatus::Processing->value,
            IvrImportStatus::Deleting->value,
            IvrImportStatus::Reverting->value,
        ], true)) {
            return back()->with('status', 'This raw import is still running and cannot be deleted yet.');
        }

        if ($import->reverted_at !== null) {
            return back()->with('status', 'This raw import has already been deleted.');
        }

        $validated = $request->validate([
            'delete_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $import->forceFill([
            'status' => IvrImportStatus::Deleting,
            'error_message' => null,
            'summary' => array_merge($import->summary ?? [], [
                'delete_progress' => [
                    'stage' => 'queued',
                    'stage_label' => 'Delete queued',
                    'processed' => 0,
                    'total' => RawImportDeleter::DELETE_STEPS,
                    'percent' => 0,
                    'source_rows_deleted' => 0,
                    'phone_numbers_deleted' => 0,
                    'clients_deleted' => 0,
                ],
            ]),
        ])->save();

        $import->broadcastProgress();

        DeleteRawIvrImport::dispatch(
            $import->id,
            $request->user()?->id,
            $validated['delete_reason'] ?? null,
        );

        return redirect()
            ->route('modules.ivr.imports.index')
            ->with('status', "Raw import {$import->original_file_name} is being deleted. The status will update automatically.");
    }

    public function status(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->take(50)
            ->values();

        $imports = IvrImport::query()
            ->where('type', IvrImportType::RawContacts)
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (IvrImport $import): array => IvrImportStatusPayload::make($import))
            ->values();

        return response()->json(['imports' => $imports]);
    }
}
