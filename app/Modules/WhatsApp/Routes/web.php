<?php

use App\Modules\WhatsApp\Http\Controllers\WhatsAppCampaignController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppImportController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppNumberController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppReportController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppUnsubscriberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('whatsapp')
    ->name('modules.whatsapp.')
    ->group(function (): void {
        Route::get('/', WhatsAppController::class)->name('index');

        Route::get('/imports', [WhatsAppImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [WhatsAppImportController::class, 'store'])->name('imports.store');
        Route::delete('/imports/{import}', [WhatsAppImportController::class, 'destroy'])->name('imports.destroy');

        Route::get('/campaigns', [WhatsAppCampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/{campaign}/export', [WhatsAppCampaignController::class, 'exportLeads'])->name('campaigns.export');
        Route::get('/campaigns/{campaign}', [WhatsAppCampaignController::class, 'show'])->name('campaigns.show');

        Route::get('/numbers', [WhatsAppNumberController::class, 'index'])->name('numbers.index');
        Route::get('/numbers/{number}', [WhatsAppNumberController::class, 'show'])->name('numbers.show');

        Route::get('/unsubscribers', [WhatsAppUnsubscriberController::class, 'index'])->name('unsubscribers.index');
        Route::post('/unsubscribers', [WhatsAppUnsubscriberController::class, 'store'])->name('unsubscribers.store');
        Route::delete('/unsubscribers/{suppression}', [WhatsAppUnsubscriberController::class, 'destroy'])->name('unsubscribers.destroy');

        Route::get('/reports', [WhatsAppReportController::class, 'index'])->name('reports.index');
    });
