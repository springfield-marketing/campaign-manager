<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContactSuppression;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppImportType;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppUnsubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $imports = WhatsAppImport::query()
            ->where('type', WhatsAppImportType::Unsubscribers->value)
            ->latest()
            ->paginate(10, ['*'], 'imports_page');

        $query = ContactSuppression::query()
            ->with('phoneNumber.client')
            ->where('channel', 'whatsapp')
            ->whereNull('released_at')
            ->latest('suppressed_at');

        if ($request->filled('phone')) {
            $query->whereHas('phoneNumber', fn ($q) => $q->where('normalized_phone', 'like', '%'.$request->string('phone').'%'));
        }

        if ($request->filled('name')) {
            $query->whereHas('phoneNumber.client', fn ($q) => $q->where('full_name', 'like', '%'.$request->string('name').'%'));
        }

        return view('whatsapp::unsubscribers.index', [
            'imports' => $imports,
            'unsubscribers' => $query->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $originalFileName = $request->file('file')->getClientOriginalName();
        $storedPath = $request->file('file')->store('whatsapp/unsubscribers', 'local');

        WhatsAppImport::create([
            'type' => WhatsAppImportType::Unsubscribers->value,
            'status' => WhatsAppImportStatus::Pending->value,
            'original_file_name' => $originalFileName,
            'stored_file_name' => basename($storedPath),
            'storage_path' => $storedPath,
            'uploaded_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('modules.whatsapp.unsubscribers.index')
            ->with('status', 'Unsubscriber import queued.');
    }

    public function destroy(ContactSuppression $suppression): RedirectResponse
    {
        abort_unless($suppression->channel === 'whatsapp', 404);

        $suppression->update(['released_at' => now()]);

        return back()->with('status', 'Number removed from WhatsApp unsubscribers.');
    }
}
