<?php

use App\Modules\IVR\Http\Controllers\IvrCampaignResultController;
use App\Modules\IVR\Http\Controllers\IvrImportController;
use App\Modules\IVR\Http\Controllers\IvrNumberController;
use App\Modules\IVR\Http\Controllers\IvrScriptController;
use App\Modules\IVR\Http\Controllers\IvrSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('ivr')
    ->name('modules.ivr.')
    ->group(function (): void {
        // ── Raw contacts import (not yet in Filament) ────────────────────────
        Route::get('/imports', [IvrImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [IvrImportController::class, 'store'])->middleware('throttle:file-uploads')->name('imports.store');
        Route::get('/imports/status', [IvrImportController::class, 'status'])->name('imports.status');
        Route::delete('/imports/{import}', [IvrImportController::class, 'destroy'])->name('imports.destroy');
        Route::get('/imports/{import}', [IvrImportController::class, 'show'])->name('imports.show');

        // ── Campaign results: endpoints with no Filament equivalent ──────────
        Route::get('/campaign-results/export', [IvrCampaignResultController::class, 'export'])->middleware('throttle:exports')->name('results.export');
        Route::get('/campaign-results/{campaign}/leads/export', [IvrCampaignResultController::class, 'exportLeads'])->middleware('throttle:exports')->name('results.leads.export');
        Route::get('/campaign-results/{campaign}/audio', [IvrCampaignResultController::class, 'audio'])->name('results.audio');
        Route::patch('/campaign-results/{campaign}/script', [IvrCampaignResultController::class, 'assignScript'])->name('results.script.assign');

        // ── Scripts: audio serving (no Filament audio player yet) ───────────
        Route::get('/scripts/{script}/audio', [IvrScriptController::class, 'audio'])->name('scripts.audio');

        // ── Numbers: export + features not yet in Filament ──────────────────
        Route::get('/numbers/export', [IvrNumberController::class, 'export'])->middleware('throttle:exports')->name('numbers.export');
        Route::patch('/numbers/{number}/tags', [IvrNumberController::class, 'updateTags'])->name('numbers.tags.update');
        Route::post('/numbers/{number}/interactions', [IvrNumberController::class, 'storeInteraction'])->name('numbers.interactions.store');

        // ── Settings: file download (linked from Filament Blade template) ────
        Route::get('/settings/database-export/{export}', [IvrSettingsController::class, 'downloadDatabaseExport'])->name('settings.database-export.download');
    });
