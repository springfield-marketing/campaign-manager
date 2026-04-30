<?php

namespace App\Http\Controllers;

use App\Support\Modules\ModuleRegistry;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ModuleRegistry $modules): View
    {
        return view('dashboard', [
            'modules' => $modules->all(),
        ]);
    }
}
