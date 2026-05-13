<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppImportType;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppCampaignResultsImport;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppRawImport;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppImportStatusPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppImportController extends Controller
{
    public function index(): View
    {
        return view('whatsapp::imports.index', [
            'imports' => WhatsAppImport::query()
                ->where('type', WhatsAppImportType::RawContacts)
                ->latest()
                ->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            ->exists();

        if ($existing) {
            return back()
                ->withErrors(['file' => "A raw import named {$originalFileName} already exists. Rename the file if this is a new upload."])
                ->withInput();
        }

        $storedPath = $validated['file']->store('whatsapp/imports/raw', 'local');

        $import = WhatsAppImport::create([
            'type'               => WhatsAppImportType::RawContacts,
            'status'             => WhatsAppImportStatus::Pending,
            'original_file_name' => $originalFileName,
            'stored_file_name'   => basename($storedPath),
            'storage_path'       => $storedPath,
            'source_name'        => $validated['source_name'] ?: null,
            'uploaded_by'        => $request->user()?->id,
        ]);

        $import->broadcastProgress();

        ProcessWhatsAppRawImport::dispatch($import->id);

        return redirect()
            ->route('modules.whatsapp.imports.index')
            ->with('status', 'Raw contact import queued successfully.');
    }

    public function destroy(WhatsAppImport $import): RedirectResponse
    {
        if ($import->type !== WhatsAppImportType::RawContacts->value) {
            abort(404);
        }

        if (in_array($import->status, [
            WhatsAppImportStatus::Pending->value,
            WhatsAppImportStatus::Processing->value,
        ], true)) {
            return back()->with('status', 'This import is still running and cannot be deleted yet.');
        }

        if ($import->reverted_at !== null) {
            return back()->with('status', 'This import has already been deleted.');
        }

        // Mark reverted (full revert job can be added later)
        $import->forceFill([
            'status'      => WhatsAppImportStatus::Reverted,
            'reverted_at' => now(),
            'reverted_by' => request()->user()?->id,
        ])->save();

        return redirect()
            ->route('modules.whatsapp.imports.index')
            ->with('status', "Import {$import->original_file_name} marked as reverted.");
    }

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
}
