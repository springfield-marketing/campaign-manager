<?php

use App\Modules\Emails\Http\Controllers\EmailsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('emails')
    ->name('modules.emails.')
    ->group(function (): void {
        Route::get('/', EmailsController::class)->name('index');
    });
