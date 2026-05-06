<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class WhatsAppController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('modules.whatsapp.campaigns.index');
    }
}
