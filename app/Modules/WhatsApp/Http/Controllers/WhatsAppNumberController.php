<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppNumberController extends Controller
{
    public function index(Request $request): View
    {
        $query = ClientPhoneNumber::query()
            ->with('client')
            ->whereHas('whatsAppMessages')
            ->latest();

        if ($request->filled('phone')) {
            $query->where('normalized_phone', 'like', '%'.$request->string('phone').'%');
        }

        $numbers = $query->paginate(50);

        return view('whatsapp::numbers.index', [
            'numbers' => $numbers,
        ]);
    }
}
