<?php

use App\Modules\WhatsApp\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('whatsapp')
    ->name('modules.whatsapp.')
    ->group(function (): void {
        Route::get('/', WhatsAppController::class)->name('index');
    });
