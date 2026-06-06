<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Enums\InteractionType;
use App\Http\Controllers\Controller;
use App\Models\ClientInteraction;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\Ownership;
use App\Models\Project;
use App\Models\Tag;
use App\Modules\IVR\Support\NumberEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IvrNumberController extends Controller
{
    public function index(Request $request): View
    {
        $numbers = $this->filteredQuery($request)
            ->with(['ivrProfile', 'sources' => fn ($query) => $query->latest()->limit(5)])
            ->paginate(25)
            ->withQueryString();

        return view('ivr::numbers.index', [
            'numbers' => $numbers,
            'stats'   => $this->numberStats($request),
            'availableSources' => DB::table('client_sources')
                ->where('channel', 'ivr')
                ->where('source_type', 'raw_import')
                ->whereNotNull('source_name')
                ->distinct()
                ->orderBy('source_name')
                ->pluck('source_name'),
            'marketingAreas' => MarketingArea::active()->orderBy('emirate')->orderBy('name')->get()->groupBy('emirate'),
            'projects'       => Project::active()->orderBy('name')->get(),
        ]);
    }

    public function show(ClientPhoneNumber $number): View
    {
        abort_unless($number->is_uae, 404);

        $number->load([
            'ivrProfile',
            'client.primaryEmail',
            'client.tags',
            'client.phoneNumbers' => fn ($query) => $query
                ->withCount(['ivrCallRecords as ivr_use_count'])
                ->with('ivrProfile')
                ->orderByDesc('is_primary')
                ->orderBy('priority')
                ->orderBy('normalized_phone'),
            'sources'        => fn ($query) => $query->latest(),
            'ivrCallRecords' => fn ($query) => $query->with('campaign')->latest('call_time'),
            'suppressions'   => fn ($query) => $query->latest('suppressed_at'),
        ]);

        $ownerships = $number->client
            ? Ownership::with(['marketingArea', 'officialArea', 'project', 'building'])
                ->where('client_id', $number->client->id)
                ->latest()
                ->get()
            : collect();

        $interactions = $number->client
            ? ClientInteraction::where('client_id', $number->client->id)
                ->latest('created_at')
                ->limit(30)
                ->get()
            : collect();

        return view('ivr::numbers.show', [
            'number'       => $number,
            'ownerships'   => $ownerships,
            'interactions' => $interactions,
            'marketingAreas' => MarketingArea::active()->orderBy('emirate')->orderBy('name')->get()->groupBy('emirate'),
            'allTags'      => Tag::orderBy('name')->get(),
        ]);
    }

    public function updateClient(Request $request, ClientPhoneNumber $number): RedirectResponse
    {
        abort_unless($number->is_uae, 404);
        abort_unless($number->client, 404);

        $data = $request->validate([
            'full_name'   => ['nullable', 'string', 'max:255'],
            'email'       => ['nullable', 'email', 'max:255'],
            'gender'      => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'interest'    => ['nullable', 'string', 'max:255'],
            'country_iso' => ['nullable', 'string', 'size:2'],
            'emirate'     => ['nullable', 'string', 'max:100'],
        ]);

        $email = $data['email'] ?? null;
        unset($data['email']);

        DB::transaction(function () use ($number, $data, $email): void {
            $number->client->update($data);
            $number->client->setPrimaryEmailAddress($email);
        });

        return redirect()->route('modules.ivr.numbers.show', $number)
            ->with('status', 'Client details updated.');
    }

    public function updateTags(Request $request, ClientPhoneNumber $number): RedirectResponse
    {
        abort_unless($number->is_uae, 404);
        abort_unless($number->client, 404);

        $request->validate(['tags' => ['nullable', 'array'], 'tags.*' => ['integer', 'exists:tags,id']]);

        $number->client->tags()->sync($request->input('tags', []));

        return redirect()->route('modules.ivr.numbers.show', $number)
            ->with('status', 'Tags updated.');
    }

    public function storeInteraction(Request $request, ClientPhoneNumber $number): RedirectResponse
    {
        abort_unless($number->is_uae, 404);
        abort_unless($number->client, 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'source'      => ['nullable', 'string', 'max:255'],
        ]);

        ClientInteraction::log(
            clientId: $number->client->id,
            type: InteractionType::Note,
            source: $data['source'] ?? null,
            description: $data['description'],
        );

        return redirect()->route('modules.ivr.numbers.show', $number)
            ->with('status', 'Note logged.');
    }

    public function suppress(ClientPhoneNumber $number, NumberEligibilityService $eligibilityService): RedirectResponse
    {
        abort_unless($number->is_uae, 404);

        DB::transaction(function () use ($number, $eligibilityService): void {
            ContactSuppression::firstOrCreate(
                [
                    'client_phone_number_id' => $number->id,
                    'channel'               => 'ivr',
                    'reason'                => 'customer_unsubscribed',
                ],
                [
                    'context'       => ['source' => 'manual'],
                    'suppressed_at' => now(),
                ],
            );

            $number->forceFill(['unsubscribed_at' => $number->unsubscribed_at ?? now()])->save();

            $eligibilityService->refresh($number->refresh());
        });

        return back()->with('status', 'Number marked as unsubscribed.');
    }

    public function unsuppress(ClientPhoneNumber $number, NumberEligibilityService $eligibilityService): RedirectResponse
    {
        abort_unless($number->is_uae, 404);

        DB::transaction(function () use ($number, $eligibilityService): void {
            ContactSuppression::query()
                ->where('client_phone_number_id', $number->id)
                ->where('channel', 'ivr')
                ->where('reason', 'customer_unsubscribed')
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            $hasOtherActiveSuppression = $number->suppressions()
                ->whereNull('released_at')
                ->where(function (Builder $query): void {
                    $query->whereNull('channel')->orWhere('channel', 'ivr');
                })
                ->exists();

            if (! $hasOtherActiveSuppression) {
                $number->forceFill(['unsubscribed_at' => null])->save();
            }

            $eligibilityService->refresh($number->refresh());
        });

        return back()->with('status', 'Unsubscribe removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $limit = $this->exportLimit($request);

        $numbers = $this->eligibleExportQuery($request)
            ->with('client')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get();

        return response()->streamDownload(function () use ($numbers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['phone', 'name']);

            foreach ($numbers as $number) {
                fputcsv($handle, [
                    $number->normalized_phone,
                    $number->client?->full_name,
                ]);
            }

            fclose($handle);
        }, 'ivr_numbers_export.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function eligibleExportQuery(Request $request, bool $applyStatusFilter = true): Builder
    {
        $digitsSql = "replace(replace(replace(replace(replace(replace(coalesce(client_phone_numbers.normalized_phone, client_phone_numbers.raw_phone, ''), '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";

        $rankedNumbers = $this->filteredQuery($request, $applyStatusFilter)
            ->where(function (Builder $query): void {
                $query->whereNull('ivr_phone_profiles.client_phone_number_id')
                    ->orWhere('ivr_phone_profiles.usage_status', 'active');
            })
            ->whereRaw("NOT ({$digitsSql} LIKE '971%' AND length({$digitsSql}) < 12)")
            ->whereNull('unsubscribed_at')
            ->where(function (Builder $query): void {
                $query->whereNull('ivr_phone_profiles.cooldown_until')
                    ->orWhere('ivr_phone_profiles.cooldown_until', '<=', now());
            })
            ->whereDoesntHave('suppressions', function (Builder $query): void {
                $query->whereNull('released_at')
                    ->where(function (Builder $query): void {
                        $query->whereNull('channel')
                            ->orWhere('channel', 'ivr');
                    });
            })
            ->addSelect(DB::raw('ivr_phone_profiles.last_called_at AS ivr_last_called_at'))
            ->addSelect(DB::raw(
                'ROW_NUMBER() OVER (
                    PARTITION BY COALESCE(client_phone_numbers.client_id, -client_phone_numbers.id)
                    ORDER BY client_phone_numbers.is_primary DESC, client_phone_numbers.priority ASC, ivr_phone_profiles.last_called_at ASC, client_phone_numbers.id ASC
                ) AS export_rank'
            ))
            ->reorder();

        return ClientPhoneNumber::query()
            ->fromSub($rankedNumbers, 'client_phone_numbers')
            ->where('export_rank', 1)
            ->orderByDesc('is_primary')
            ->orderBy('priority')
            ->orderBy('ivr_last_called_at')
            ->orderBy('id');
    }

    private function filteredQuery(Request $request, bool $applyStatusFilter = true): Builder
    {
        $query = ClientPhoneNumber::query()
            ->where('is_uae', true)
            ->whereHas('client', function (Builder $query): void {
                $query->whereNotNull('full_name')
                    ->whereRaw("trim(full_name) <> ''");
            })
            ->withCount(['ivrCallRecords as ivr_use_count'])
            ->leftJoin('ivr_phone_profiles', 'ivr_phone_profiles.client_phone_number_id', '=', 'client_phone_numbers.id');

        $includeSources = $this->selectedSources($request, 'source_include');
        $excludeSources = $this->selectedSources($request, 'source_exclude');

        if ($request->filled('phone')) {
            $phone = trim((string) $request->input('phone'));
            $phoneDigits = preg_replace('/\D+/', '', $phone) ?: null;

            $query->where(function (Builder $query) use ($phone, $phoneDigits): void {
                $query->where('normalized_phone', 'like', '%'.$phone.'%')
                    ->orWhere('raw_phone', 'like', '%'.$phone.'%');

                if ($phoneDigits) {
                    $query->orWhere('normalized_phone', 'like', '%'.$phoneDigits.'%')
                        ->orWhere('raw_phone', 'like', '%'.$phoneDigits.'%')
                        ->orWhere('national_number', 'like', '%'.$phoneDigits.'%');
                }
            });
        }

        if ($includeSources !== []) {
            $query->whereHas('sources', fn ($builder) => $builder
                ->where('source_type', 'raw_import')
                ->whereIn('source_name', $includeSources));
        } elseif ($request->filled('source')) {
            $query->whereHas('sources', fn ($builder) => $builder
                ->where('source_type', 'raw_import')
                ->where('source_name', $request->string('source')));
        }

        if ($excludeSources !== []) {
            $query->whereDoesntHave('sources', fn ($builder) => $builder
                ->where('source_type', 'raw_import')
                ->whereIn('source_name', $excludeSources));
        }

        if ($request->filled('emirate')) {
            $query->whereHas('client', fn ($builder) => $builder->where('emirate', $request->string('emirate')));
        }

        if ($request->filled('marketing_area')) {
            $query->whereHas('client.ownerships', fn ($builder) => $builder
                ->where('marketing_area_id', $request->integer('marketing_area')));
        }

        if ($request->filled('project')) {
            $query->whereHas('client.ownerships', fn ($builder) => $builder
                ->where('project_id', $request->integer('project')));
        }

        if ($applyStatusFilter && $request->filled('status')) {
            $status = (string) $request->string('status');
            $query->where(function (Builder $query) use ($status): void {
                if ($status === 'active') {
                    $query->whereNull('ivr_phone_profiles.client_phone_number_id');
                }
                $query->orWhere('ivr_phone_profiles.usage_status', $status);
            });
        }

        if ($request->filled('uses_min')) {
            $query->has('ivrCallRecords', '>=', (int) $request->integer('uses_min'));
        }

        if ($request->filled('uses_max')) {
            $query->has('ivrCallRecords', '<=', (int) $request->integer('uses_max'));
        }

        return $query->orderByRaw("COALESCE(ivr_phone_profiles.usage_status, 'active')")
            ->orderByDesc('ivr_phone_profiles.last_called_at');
    }

    /**
     * @return array<int, string>
     */
    private function selectedSources(Request $request, string $key): array
    {
        $value = $request->input($key, []);
        $sources = is_array($value) ? $value : [$value];

        return collect($sources)
            ->map(fn ($source): string => trim((string) $source))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function exportLimit(Request $request): ?int
    {
        if ($request->input('export_limit') === 'all') {
            return null;
        }

        $limit = (int) ($request->integer('export_limit') ?: 1000);

        return min(max($limit, 1), 50000);
    }

    /**
     * @return array<string, int>
     */
    private function numberStats(Request $request): array
    {
        $base = $this->filteredQuery($request, applyStatusFilter: false)->reorder();

        $active = (clone $base)
            ->where(function (Builder $query): void {
                $query->whereNull('ivr_phone_profiles.client_phone_number_id')
                    ->orWhere('ivr_phone_profiles.usage_status', 'active');
            })
            ->count();

        $inactive = (clone $base)
            ->where('ivr_phone_profiles.usage_status', 'inactive')
            ->count();

        $dead = (clone $base)
            ->where('ivr_phone_profiles.usage_status', 'dead')
            ->count();

        $unsubscribers = (clone $base)
            ->where(function (Builder $query): void {
                $query->whereNotNull('unsubscribed_at')
                    ->orWhereHas('suppressions', function (Builder $query): void {
                        $query->whereNull('released_at')
                            ->where(function (Builder $query): void {
                                $query->whereNull('channel')
                                    ->orWhere('channel', 'ivr');
                            });
                    });
            })
            ->count();

        $cooldown = (clone $base)
            ->where('ivr_phone_profiles.cooldown_until', '>', now())
            ->count();

        return [
            'total' => (clone $base)->count(),
            'active' => $active,
            'inactive' => $inactive,
            'dead' => $dead,
            'unsubscribers' => $unsubscribers,
            'cooldown' => $cooldown,
        ];
    }
}
