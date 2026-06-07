<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Models\IvrScript;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IvrScriptController extends Controller
{
    public function audio(IvrScript $script): BinaryFileResponse
    {
        abort_unless($script->audio_file_path, 404);
        $path = storage_path('app/private/'.$script->audio_file_path);
        abort_unless(file_exists($path), 404);

        return response()->file($path);
    }
}
