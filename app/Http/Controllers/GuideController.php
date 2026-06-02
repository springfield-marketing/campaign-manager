<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class GuideController extends Controller
{
    public function show(): View
    {
        return view('guide');
    }
}
