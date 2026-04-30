<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Support\IvrReportData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IVRController extends Controller
{
    public function __invoke(Request $request, IvrReportData $reports): View
    {
        $year = (int) ($request->integer('year') ?: now()->year);
        $month = $request->has('month') ? ($request->integer('month') ?: null) : now()->month;

        return view('ivr::reports.index', $reports->forPeriod($year, $month));
    }
}
