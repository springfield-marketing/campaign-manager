<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        if (in_array($import->status, ['pending', 'processing'], true)) {
            return back()->with('status', 'This raw import is still running and cannot be reverted yet.');
        }

        if ($import->reverted_at !== null) {
            return back()->with('status', 'This raw import has already been reverted.');
        }

        $validated = $request->validate([
            'revert_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $affectedPhoneIds = collect();
        $deletedPhoneCount = 0;
        $deletedClientCount = 0;

        DB::transaction(function () use ($import, $request, $validated, &$affectedPhoneIds, &$deletedPhoneCount, &$deletedClientCount): void {
            $affectedPhoneIds = ClientSource::query()
                ->where('channel', 'ivr')
                ->where('source_type', 'raw_import')
                ->where('source_reference', (string) $import->id)
                ->pluck('client_phone_number_id')
                ->filter()
                ->unique()
                ->values();

            ClientSource::query()
                ->where('channel', 'ivr')
                ->where('source_type', 'raw_import')
                ->where('source_reference', (string) $import->id)
                ->delete();

            ClientPhoneNumber::query()
                ->whereIn('id', $affectedPhoneIds)
                ->with(['client', 'sources', 'suppressions', 'ivrCallRecords'])
                ->get()
                ->each(function (ClientPhoneNumber $phoneNumber) use (&$deletedPhoneCount, &$deletedClientCount): void {
                    $phoneNumber->load(['client', 'sources', 'suppressions', 'ivrCallRecords']);

                    if ($phoneNumber->sources->isEmpty() && $phoneNumber->suppressions->isEmpty() && $phoneNumber->ivrCallRecords->isEmpty()) {
                        $client = $phoneNumber->client;

                        $phoneNumber->delete();
                        $deletedPhoneCount++;

                        if ($client && $client->phoneNumbers()->doesntExist() && $client->sources()->doesntExist()) {
                            $client->delete();
                            $deletedClientCount++;
                        }

                        return;
                    }

                    $latestRawSource = $phoneNumber->sources()
                        ->where('channel', 'ivr')
                        ->where('source_type', 'raw_import')
                        ->latest()
                        ->first();

                    $phoneNumber->forceFill([
                        'last_source_name' => $latestRawSource?->source_name,
                        'last_imported_at' => $latestRawSource?->created_at,
                    ])->save();
                });

            $import->update([
                'status' => 'reverted',
                'reverted_at' => now(),
                'reverted_by' => $request->user()?->id,
                'revert_reason' => $validated['revert_reason'] ?? null,
            ]);
        });

        Log::channel('ivr')->info('Reverted raw IVR import.', [
            'import_id' => $import->id,
            'file_name' => $import->original_file_name,
            'affected_phone_numbers' => $affectedPhoneIds->count(),
            'deleted_phone_numbers' => $deletedPhoneCount,
            'deleted_clients' => $deletedClientCount,
        ]);

        return redirect()
            ->route('modules.ivr.imports.index')
            ->with('status', "Raw import {$import->original_file_name} was reverted.");
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
                'is_active' => in_array($import->status, ['pending', 'processing'], true),
            ])
            ->values();

        return response()->json(['imports' => $imports]);
    }
}
