<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppLeadController extends Controller
{
    public function index(Request $request): View
    {
        $query = WhatsAppMessage::query()
            ->with(['campaign', 'phoneNumber.client'])
            ->where('has_quick_replies', true)
            ->whereNull('quick_reply_3')
            ->where(function ($q): void {
                $q->whereNotNull('quick_reply_1')
                    ->orWhereNotNull('quick_reply_2');
            })
            ->latest('scheduled_at');

        if ($request->filled('phone')) {
            $query->whereHas('phoneNumber', fn ($q) => $q->where('normalized_phone', 'like', '%'.$request->string('phone').'%'));
        }

        if ($request->filled('campaign')) {
            $query->whereHas('campaign', fn ($q) => $q->where('name', 'like', '%'.$request->string('campaign').'%'));
        }

        if ($request->filled('reply')) {
            $reply = $request->string('reply');
            $query->where(function ($q) use ($reply): void {
                $q->where('quick_reply_1', 'like', '%'.$reply.'%')
                    ->orWhere('quick_reply_2', 'like', '%'.$reply.'%');
            });
        }

        return view('whatsapp::leads.index', [
            'leads' => $query->paginate(50)->withQueryString(),
        ]);
    }
}
