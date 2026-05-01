<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Jobs\RevertRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
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
                ->where('type', 'raw_contacts')
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
            ->where('type', 'raw_contacts')
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
            'type' => 'raw_contacts',
            'status' => 'pending',
            'original_file_name' => $originalFileName,
            'stored_file_name' => basename($storedPath),
            'storage_path' => $storedPath,
            'source_name' => $validated['source_name'] ?: null,
            'uploaded_by' => $request->user()?->id,
        ]);

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
        if ($import->type !== 'raw_contacts') {
            abort(404);
        }

        if (in_array($import->status, ['pending', 'processing', 'reverting'], true)) {
            return back()->with('status', 'This raw import is still running and cannot be reverted yet.');
        }

        if ($import->reverted_at !== null) {
            return back()->with('status', 'This raw import has already been reverted.');
        }

        $validated = $request->validate([
            'revert_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $import->forceFill([
            'status' => 'reverting',
            'error_message' => null,
        ])->save();

        RevertRawIvrImport::dispatch(
            $import->id,
            $request->user()?->id,
            $validated['revert_reason'] ?? null,
        );

        return redirect()
            ->route('modules.ivr.imports.index')
            ->with('status', "Raw import {$import->original_file_name} is being reverted. The status will update automatically.");
    }

    public function status(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $imports = IvrImport::query()
            ->where('type', 'raw_contacts')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (IvrImport $import): array => [
                'id' => $import->id,
                'status' => $import->status,
                'status_label' => str_replace('_', ' ', $import->status),
                'total_rows' => $import->total_rows,
                'processed_rows' => $import->processed_rows,
                'successful_rows' => $import->successful_rows,
                'failed_rows' => $import->failed_rows,
                'duplicate_rows' => $import->duplicate_rows,
                'progress' => $import->total_rows > 0
                    ? min(100, round(($import->processed_rows / $import->total_rows) * 100))
                    : 0,
                'is_active' => in_array($import->status, ['pending', 'processing', 'reverting'], true),
            ])
            ->values();

        return response()->json(['imports' => $imports]);
    }
}
