<?php

namespace App\Modules\Emails\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class EmailsController extends Controller
{
    public function __invoke(): View
    {
        return view('emails::index', [
            'capabilities' => [
                'Audience segmentation and send orchestration',
                'Template lifecycle management and approval history',
                'Delivery monitoring, bounce handling, and reporting',
            ],
        ]);
    }
}
