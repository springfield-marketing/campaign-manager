<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Models\IvrScript;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IvrScriptController extends Controller
{
    public function index(): View
    {
        return view('ivr::scripts.index', [
            'scripts' => IvrScript::latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'audio_file'   => ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a,aac', 'max:102400'],
            'audio_script' => ['nullable', 'string', 'max:10000'],
        ], [
            'audio_file.max' => 'The audio file must be 100 MB or smaller.',
        ]);

        $audioPath = null;
        $audioOriginalName = null;

        if ($request->hasFile('audio_file') && $request->file('audio_file')->isValid()) {
            $audioPath = $validated['audio_file']->store('ivr/scripts/audio', 'local');
            $audioOriginalName = $validated['audio_file']->getClientOriginalName();
        }

        IvrScript::create([
            'name'               => $validated['name'],
            'audio_file_path'    => $audioPath,
            'audio_original_name' => $audioOriginalName,
            'audio_script'       => $validated['audio_script'] ?? null,
        ]);

        return back()->with('status', 'Script saved.');
    }

    public function audio(IvrScript $script): BinaryFileResponse
    {
        abort_unless($script->audio_file_path, 404);
        $path = storage_path('app/private/'.$script->audio_file_path);
        abort_unless(file_exists($path), 404);

        return response()->file($path);
    }

    public function destroy(IvrScript $script): RedirectResponse
    {
        if ($script->audio_file_path) {
            Storage::disk('local')->delete($script->audio_file_path);
        }

        $script->delete();

        return back()->with('status', 'Script deleted.');
    }
}
