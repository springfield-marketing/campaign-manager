<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Models\IvrSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IvrSettingsController extends Controller
{
    public function edit(): View
    {
        return view('ivr::settings.index', [
            'settings' => IvrSettings::current(),
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
}
