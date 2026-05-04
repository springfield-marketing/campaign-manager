<?php

use App\Modules\IVR\Http\Controllers\IVRController;
use App\Modules\IVR\Http\Controllers\IvrCampaignResultController;
use App\Modules\IVR\Http\Controllers\IvrImportController;
use App\Modules\IVR\Http\Controllers\IvrNumberController;
use App\Modules\IVR\Http\Controllers\IvrReportController;
use App\Modules\IVR\Http\Controllers\IvrSettingsController;
use App\Modules\IVR\Http\Controllers\IvrUnsubscriberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('ivr')
    ->name('modules.ivr.')
    ->group(function (): void {
        Route::get('/', IVRController::class)->name('index');
        Route::get('/imports', [IvrImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [IvrImportController::class, 'store'])->name('imports.store');
        Route::get('/imports/status', [IvrImportController::class, 'status'])->name('imports.status');
        Route::delete('/imports/{import}', [IvrImportController::class, 'destroy'])->name('imports.destroy');
        Route::get('/imports/{import}', [IvrImportController::class, 'show'])->name('imports.show');
        Route::get('/campaign-results', [IvrCampaignResultController::class, 'index'])->name('results.index');
        Route::post('/campaign-results', [IvrCampaignResultController::class, 'store'])->name('results.store');
        Route::get('/campaign-results/status', [IvrCampaignResultController::class, 'status'])->name('results.status');
        Route::delete('/campaign-results/imports/{import}', [IvrCampaignResultController::class, 'destroy'])->name('results.destroy');
        Route::get('/campaign-results/{campaign}/leads/export', [IvrCampaignResultController::class, 'exportLeads'])->name('results.leads.export');
        Route::get('/campaign-results/{campaign}', [IvrCampaignResultController::class, 'show'])->name('results.show');
        Route::get('/numbers', [IvrNumberController::class, 'index'])->name('numbers.index');
        Route::get('/numbers/export', [IvrNumberController::class, 'export'])->name('numbers.export');
        Route::get('/numbers/{number}', [IvrNumberController::class, 'show'])->name('numbers.show');
        Route::get('/unsubscribers', [IvrUnsubscriberController::class, 'index'])->name('unsubscribers.index');
        Route::post('/unsubscribers', [IvrUnsubscriberController::class, 'store'])->name('unsubscribers.store');
        Route::get('/unsubscribers/status', [IvrUnsubscriberController::class, 'status'])->name('unsubscribers.status');
        Route::delete('/unsubscribers/{suppression}', [IvrUnsubscriberController::class, 'destroy'])->name('unsubscribers.destroy');
        Route::get('/reports', [IvrReportController::class, 'index'])->name('reports.index');
        Route::get('/settings', [IvrSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [IvrSettingsController::class, 'update'])->name('settings.update');
    });
