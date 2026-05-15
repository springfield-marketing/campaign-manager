<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\Community;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppNumberController extends Controller
{
    public function index(Request $request): View
    {
        $numbers = $this->buildQuery($request)
            ->simplePaginate(50)
            ->withQueryString();

        return view('whatsapp::numbers.index', [
            'numbers'     => $numbers,
            'stats'       => $this->stats(),
            'origins'     => $this->distinctOrigins(),
            'names'       => $this->distinctNames(),
            'regions'     => $this->availableRegions(),
            'communities' => $this->availableCommunities(),
            'statuses'    => ['active', 'cooldown', 'dead'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $numbers = $this->buildQuery($request)
            ->where(function (Builder $q): void {
                // Exclude dead numbers and numbers still in active cooldown
                $q->whereNull('whatsapp_phone_profiles.usage_status')
                  ->orWhere('whatsapp_phone_profiles.usage_status', 'active')
                  ->orWhere(function (Builder $q2): void {
                      $q2->where('whatsapp_phone_profiles.usage_status', 'cooldown')
                         ->where('whatsapp_phone_profiles.cooldown_until', '<=', now());
                  });
            })
            ->with('client.region')
            ->get();

        $filename = 'whatsapp-numbers-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($numbers): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['phone', 'name', 'emirate', 'origin', 'source', 'messages', 'suppressed']);

            foreach ($numbers as $number) {
                fputcsv($handle, [
                    $number->normalized_phone,
                    $number->client?->full_name,
                    $number->client?->region?->name,
                    $number->detected_country,
                    $number->last_source_name,
                    $number->whats_app_messages_count,
                    $number->suppressed ? 'yes' : 'no',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function show(ClientPhoneNumber $number): View
    {
        abort_unless($number->whatsAppMessages()->exists(), 404);

        $number->load([
            'client.region',
            'client.community',
            'client.phoneNumbers' => fn ($q) => $q
                ->withCount('whatsAppMessages')
                ->orderByDesc('is_primary')
                ->orderBy('normalized_phone'),
            'sources'          => fn ($q) => $q->where('channel', 'whatsapp')->latest(),
            'whatsAppMessages' => fn ($q) => $q->with('campaign')->latest('scheduled_at'),
            'suppressions'     => fn ($q) => $q->where('channel', 'whatsapp')->whereNull('released_at')->latest('suppressed_at'),
            'whatsAppProfile',
        ]);

        return view('whatsapp::numbers.show', [
            'number'  => $number,
            'regions' => Region::with('communities')->orderBy('name')->get(),
        ]);
    }

    public function updateClient(Request $request, ClientPhoneNumber $number): RedirectResponse
    {
        abort_unless($number->client, 404);

        $data = $request->validate([
            'full_name'    => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'gender'       => ['nullable', 'string', 'max:50'],
            'region_id'    => ['nullable', 'integer', 'exists:regions,id'],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'nationality'  => ['nullable', 'string', 'max:100'],
            'resident'     => ['nullable', 'string', 'max:100'],
            'interest'     => ['nullable', 'string', 'max:255'],
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
            'normalized_phone'    => [
                'required', 'string', 'max:30',
                Rule::unique('client_phone_numbers', 'normalized_phone')->ignore($number->id),
            ],
            'raw_phone'           => ['nullable', 'string', 'max:30'],
            'country_code'        => ['nullable', 'string', 'max:10'],
            'national_number'     => ['nullable', 'string', 'max:20'],
            'detected_country'    => ['nullable', 'string', 'max:10'],
            'label'               => ['nullable', 'string', 'max:100'],
            'priority'            => ['required', 'integer', 'min:0', 'max:9999'],
            'verification_status' => ['required', 'string', Rule::in(['unverified', 'verified', 'invalid'])],
            'is_primary'          => ['boolean'],
            'is_whatsapp'         => ['boolean'],
            'is_uae'              => ['boolean'],
        ]);

        $data['is_primary']  = $request->boolean('is_primary');
        $data['is_whatsapp'] = $request->boolean('is_whatsapp');
        $data['is_uae']      = $request->boolean('is_uae');

        $number->update($data);

        return redirect()->route('modules.whatsapp.numbers.show', $number)
            ->with('status', 'Phone number updated.');
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function buildQuery(Request $request): Builder
    {
        $query = ClientPhoneNumber::query()
            ->select('client_phone_numbers.*')
            ->selectRaw('COUNT(whatsapp_messages.id) as whats_app_messages_count')
            ->selectRaw('EXISTS(SELECT 1 FROM contact_suppressions WHERE contact_suppressions.client_phone_number_id = client_phone_numbers.id AND contact_suppressions.channel = ? AND contact_suppressions.released_at IS NULL) as suppressed', ['whatsapp'])
            ->selectRaw("COALESCE(whatsapp_phone_profiles.usage_status, 'active') as wp_usage_status")
            ->selectRaw('whatsapp_phone_profiles.cooldown_until as wp_cooldown_until')
            ->join('whatsapp_messages', 'whatsapp_messages.client_phone_number_id', '=', 'client_phone_numbers.id')
            ->leftJoin('whatsapp_phone_profiles', 'whatsapp_phone_profiles.client_phone_number_id', '=', 'client_phone_numbers.id')
            ->with('client.region')
            ->groupBy('client_phone_numbers.id', 'whatsapp_phone_profiles.usage_status', 'whatsapp_phone_profiles.cooldown_until')
            ->orderByDesc('client_phone_numbers.created_at');

        if ($request->filled('phone')) {
            $query->where('client_phone_numbers.normalized_phone', 'like', '%' . $request->string('phone') . '%');
        }

        if ($request->filled('name')) {
            $query->whereHas('client', fn ($q) => $q->where('full_name', 'like', '%' . $request->string('name') . '%'));
        }

        if ($request->filled('origin')) {
            $query->where('client_phone_numbers.detected_country', $request->string('origin'));
        }

        if ($request->filled('region')) {
            $query->whereHas('client', fn ($q) => $q->where('region_id', $request->integer('region')));
        }

        if ($request->filled('community')) {
            $query->whereHas('client', fn ($q) => $q->where('community_id', $request->integer('community')));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'dead') {
                $query->where('whatsapp_phone_profiles.usage_status', 'dead');
            } elseif ($status === 'cooldown') {
                $query->where('whatsapp_phone_profiles.usage_status', 'cooldown')
                      ->where('whatsapp_phone_profiles.cooldown_until', '>', now());
            } elseif ($status === 'active') {
                $query->where(function (Builder $q): void {
                    $q->whereNull('whatsapp_phone_profiles.usage_status')
                      ->orWhere('whatsapp_phone_profiles.usage_status', 'active');
                });
            }
        }

        return $query;
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        $total = DB::table('whatsapp_messages')
            ->whereNotNull('client_phone_number_id')
            ->distinct('client_phone_number_id')
            ->count('client_phone_number_id');

        $suppressed = ClientPhoneNumber::whereHas('whatsAppMessages')
            ->whereHas('suppressions', fn ($q) => $q->where('channel', 'whatsapp')->whereNull('released_at'))
            ->count();

        $dead = DB::table('whatsapp_phone_profiles')
            ->whereIn('client_phone_number_id', function ($q): void {
                $q->select('client_phone_number_id')
                  ->from('whatsapp_messages')
                  ->whereNotNull('client_phone_number_id');
            })
            ->where('usage_status', 'dead')
            ->count();

        $origins = ClientPhoneNumber::whereHas('whatsAppMessages')
            ->whereNotNull('detected_country')
            ->distinct()
            ->count('detected_country');

        return compact('total', 'suppressed', 'dead', 'origins');
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function distinctOrigins()
    {
        return DB::table('client_phone_numbers')
            ->join('whatsapp_messages', 'whatsapp_messages.client_phone_number_id', '=', 'client_phone_numbers.id')
            ->whereNotNull('detected_country')
            ->where('detected_country', '<>', '')
            ->distinct()
            ->orderBy('detected_country')
            ->pluck('detected_country');
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function distinctNames()
    {
        return DB::table('clients')
            ->join('client_phone_numbers', 'client_phone_numbers.client_id', '=', 'clients.id')
            ->join('whatsapp_messages', 'whatsapp_messages.client_phone_number_id', '=', 'client_phone_numbers.id')
            ->whereNotNull('clients.full_name')
            ->where('clients.full_name', '<>', '')
            ->distinct()
            ->orderBy('clients.full_name')
            ->pluck('clients.full_name');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Region> */
    private function availableRegions()
    {
        return Region::whereHas('clients', fn ($q) => $q->whereHas('phoneNumbers', fn ($q2) => $q2->whereHas('whatsAppMessages')))
            ->orderBy('name')
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Community> */
    private function availableCommunities()
    {
        return Community::whereHas('clients', fn ($q) => $q->whereHas('phoneNumbers', fn ($q2) => $q2->whereHas('whatsAppMessages')))
            ->with('region')
            ->orderBy('name')
            ->get();
    }
}
