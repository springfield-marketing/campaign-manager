<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Jobs\ExportCentralDatabase;
use App\Modules\IVR\Models\CentralDatabaseExport;
use App\Modules\IVR\Models\IvrSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class IvrSettingsController extends Controller
{
    public function edit(): View
    {
        return view('ivr::settings.index', [
            'settings' => IvrSettings::current(),
            'databaseExports' => CentralDatabaseExport::query()
                ->with('requester')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_minutes_quota' => ['required', 'integer', 'min:1', 'max:10000000'],
            'price_per_minute_under' => ['required', 'numeric', 'min:0', 'max:100'],
            'price_per_minute_over' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        IvrSettings::current()->update($validated);

        return redirect()
            ->route('modules.ivr.settings.edit')
            ->with('status', 'Settings saved successfully.');
    }

    public function exportDatabase(Request $request): RedirectResponse
    {
        $runningExport = CentralDatabaseExport::query()
            ->whereIn('status', [
                CentralDatabaseExport::STATUS_PENDING,
                CentralDatabaseExport::STATUS_PROCESSING,
            ])
            ->latest()
            ->first();

        if ($runningExport) {
            return redirect()
                ->route('modules.ivr.settings.edit')
                ->with('status', 'A database export is already running.');
        }

        $export = CentralDatabaseExport::create([
            'status' => CentralDatabaseExport::STATUS_PENDING,
            'requested_by' => $request->user()?->id,
        ]);

        ExportCentralDatabase::dispatch($export->id);

        return redirect()
            ->route('modules.ivr.settings.edit')
            ->with('status', 'Database export queued. It will appear here when it is ready.');
    }

    public function downloadDatabaseExport(CentralDatabaseExport $export): StreamedResponse
    {
        abort_unless($export->status === CentralDatabaseExport::STATUS_COMPLETED, 404);
        abort_unless($export->storage_path && Storage::disk('local')->exists($export->storage_path), 404);

        return Storage::disk('local')->download(
            $export->storage_path,
            $export->file_name ?: 'central-database-export.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
