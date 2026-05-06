<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppImportType;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppImportController extends Controller
{
    public function index(): View
    {
        $imports = WhatsAppImport::query()
            ->where('type', WhatsAppImportType::CampaignResults->value)
            ->latest()
            ->paginate(15);

        return view('whatsapp::imports.index', [
            'imports' => $imports,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
        ], [
            'file.uploaded' => 'The file could not be uploaded. Check PHP upload limits.',
            'file.max' => 'The file must be 50 MB or smaller.',
        ]);

        $originalFileName = $validated['file']->getClientOriginalName();

        $existing = WhatsAppImport::query()
            ->where('type', WhatsAppImportType::CampaignResults->value)
            ->where('original_file_name', $originalFileName)
            ->whereNull('reverted_at')
            ->exists();

        if ($existing) {
            return back()
                ->withErrors(['file' => "An import named {$originalFileName} already exists."])
                ->withInput();
        }

        $storedPath = $validated['file']->store('whatsapp/imports', 'local');

        WhatsAppImport::create([
            'type' => WhatsAppImportType::CampaignResults->value,
            'status' => WhatsAppImportStatus::Pending->value,
            'original_file_name' => $originalFileName,
            'stored_file_name' => basename($storedPath),
            'storage_path' => $storedPath,
            'uploaded_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('modules.whatsapp.imports.index')
            ->with('status', 'Campaign results import queued successfully.');
    }

    public function destroy(WhatsAppImport $import): RedirectResponse
    {
        if (in_array($import->status, [
            WhatsAppImportStatus::Pending->value,
            WhatsAppImportStatus::Processing->value,
            WhatsAppImportStatus::Reverted->value,
        ], true)) {
            return back()->with('status', 'This import cannot be reverted right now.');
        }

        // TODO: implement revert job

        return redirect()
            ->route('modules.whatsapp.imports.index')
            ->with('status', "Import {$import->original_file_name} was reverted.");
    }
}
