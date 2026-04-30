<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    public function __invoke(): View
    {
        return view('whatsapp::index', [
            'capabilities' => [
                'Template management and approval workflows',
                'Conversation state handling and outbound messaging',
                'Delivery events, retries, and channel analytics',
            ],
        ]);
    }
}
