<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        if ($request->filled('name')) {
            $query->whereHas('client', fn ($q) => $q->where('full_name', 'like', '%'.$request->string('name').'%'));
        }

        if ($request->filled('origin')) {
            $query->where('client_phone_numbers.detected_country', 'like', '%'.$request->string('origin').'%');
        }

        if ($request->filled('city')) {
            $query->whereHas('client', fn ($q) => $q->where('city', 'like', '%'.$request->string('city').'%'));
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

    public function updateClient(Request $request, ClientPhoneNumber $number): RedirectResponse
    {
        abort_unless($number->client, 404);

        $data = $request->validate([
            'full_name'   => ['nullable', 'string', 'max:255'],
            'email'       => ['nullable', 'email', 'max:255'],
            'gender'      => ['nullable', 'string', 'max:50'],
            'city'        => ['nullable', 'string', 'max:100'],
            'country'     => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'community'   => ['nullable', 'string', 'max:100'],
            'resident'    => ['nullable', 'string', 'max:100'],
            'interest'    => ['nullable', 'string', 'max:255'],
        ]);

        $number->client->update($data);

        return redirect()->route('modules.whatsapp.numbers.show', $number)
            ->with('status', 'Client details updated.');
    }

    public function destroyClient(ClientPhoneNumber $number): RedirectResponse
    {
        abort_unless($number->client, 404);

        $number->client->delete();

        return redirect()->route('modules.whatsapp.numbers.index')
            ->with('status', 'Client deleted.');
    }

    public function updateNumber(Request $request, ClientPhoneNumber $number): RedirectResponse
    {
        $data = $request->validate([
            'normalized_phone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('client_phone_numbers', 'normalized_phone')->ignore($number->id),
            ],
            'raw_phone'          => ['nullable', 'string', 'max:30'],
            'country_code'       => ['nullable', 'string', 'max:10'],
            'national_number'    => ['nullable', 'string', 'max:20'],
            'detected_country'   => ['nullable', 'string', 'max:10'],
            'label'              => ['nullable', 'string', 'max:100'],
            'priority'           => ['required', 'integer', 'min:0', 'max:9999'],
            'verification_status'=> ['required', 'string', Rule::in(['unverified', 'verified', 'invalid'])],
            'is_primary'         => ['boolean'],
            'is_whatsapp'        => ['boolean'],
            'is_uae'             => ['boolean'],
        ]);

        // Checkboxes are absent from request when unchecked — default to false
        $data['is_primary']  = $request->boolean('is_primary');
        $data['is_whatsapp'] = $request->boolean('is_whatsapp');
        $data['is_uae']      = $request->boolean('is_uae');

        $number->update($data);

        return redirect()->route('modules.whatsapp.numbers.show', $number)
            ->with('status', 'Phone number updated.');
    }
}
