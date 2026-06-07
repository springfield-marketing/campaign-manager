<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Models\CentralDatabaseExport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IvrSettingsController extends Controller
{
    public function downloadDatabaseExport(CentralDatabaseExport $export): BinaryFileResponse
    {
        abort_unless($export->status === CentralDatabaseExport::STATUS_COMPLETED, 404);
        abort_unless($export->storage_path && Storage::disk('local')->exists($export->storage_path), 404);

        return response()->download(
            Storage::disk('local')->path($export->storage_path),
            $export->file_name ?: 'central-database-export.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
