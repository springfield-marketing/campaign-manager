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
            ->select('client_phone_numbers.*')
            ->selectRaw('COUNT(whatsapp_messages.id) as whats_app_messages_count')
            ->join('whatsapp_messages', 'whatsapp_messages.client_phone_number_id', '=', 'client_phone_numbers.id')
            ->with('client')
            ->groupBy('client_phone_numbers.id')
            ->orderByDesc('client_phone_numbers.created_at');

        if ($request->filled('phone')) {
            $query->where('client_phone_numbers.normalized_phone', 'like', '%'.$request->string('phone').'%');
        }

        $numbers = $query->simplePaginate(50)->withQueryString();

        return view('whatsapp::numbers.index', [
            'numbers' => $numbers,
        ]);
    }

    public function show(ClientPhoneNumber $number): View
    {
        abort_unless($number->whatsAppMessages()->exists(), 404);

        $number->load([
            'client',
            'client.phoneNumbers' => fn ($q) => $q
                ->withCount('whatsAppMessages')
                ->orderByDesc('is_primary')
                ->orderBy('normalized_phone'),
            'sources' => fn ($q) => $q->where('channel', 'whatsapp')->latest(),
            'whatsAppMessages' => fn ($q) => $q->with('campaign')->latest('scheduled_at'),
            'suppressions' => fn ($q) => $q->where('channel', 'whatsapp')->whereNull('released_at')->latest('suppressed_at'),
            'whatsAppProfile',
        ]);

        return view('whatsapp::numbers.show', [
            'number' => $number,
        ]);
    }
}
