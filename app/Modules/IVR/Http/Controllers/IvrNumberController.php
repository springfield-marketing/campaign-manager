<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientPhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class IvrNumberController extends Controller
{
    public function index(Request $request): View
    {
        $numbers = $this->filteredQuery($request)
            ->with(['client', 'sources' => fn ($query) => $query->latest()->limit(5)])
            ->paginate(25)
            ->withQueryString();

        return view('ivr::numbers.index', [
            'numbers' => $numbers,
            'availableSources' => DB::table('client_sources')
                ->where('channel', 'ivr')
                ->where('source_type', 'raw_import')
                ->whereNotNull('source_name')
                ->distinct()
                ->orderBy('source_name')
                ->pluck('source_name'),
        ]);
    }

    public function show(ClientPhoneNumber $number): View
    {
        abort_unless($number->is_uae, 404);

        return view('ivr::numbers.show', [
            'number' => $number->load([
                'client',
                'client.phoneNumbers' => fn ($query) => $query
                    ->withCount(['ivrCallRecords as ivr_use_count'])
                    ->orderByDesc('is_primary')
                    ->orderBy('priority')
                    ->orderBy('normalized_phone'),
                'sources' => fn ($query) => $query->latest(),
                'ivrCallRecords' => fn ($query) => $query->with('campaign')->latest('call_time'),
                'suppressions' => fn ($query) => $query->latest('suppressed_at'),
            ]),
        ]);
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

    private function eligibleExportQuery(Request $request): Builder
    {
        $rankedNumbers = $this->filteredQuery($request)
            ->where('usage_status', 'active')
            ->whereNull('unsubscribed_at')
            ->where(function (Builder $query): void {
                $query->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<=', now());
            })
            ->whereDoesntHave('suppressions', function (Builder $query): void {
                $query->whereNull('released_at')
                    ->where(function (Builder $query): void {
                        $query->whereNull('channel')
                            ->orWhere('channel', 'ivr');
                    });
            })
            ->addSelect(DB::raw(
                'ROW_NUMBER() OVER (
                    PARTITION BY COALESCE(client_id, -id)
                    ORDER BY is_primary DESC, priority ASC, last_called_at ASC, id ASC
                ) AS export_rank'
            ))
            ->reorder();

        return ClientPhoneNumber::query()
            ->fromSub($rankedNumbers, 'client_phone_numbers')
            ->where('export_rank', 1)
            ->orderByDesc('is_primary')
            ->orderBy('priority')
            ->orderBy('last_called_at')
            ->orderBy('id');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = ClientPhoneNumber::query()
            ->where('is_uae', true)
            ->withCount(['ivrCallRecords as ivr_use_count']);

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

        if ($request->filled('city')) {
            $query->whereHas('client', fn ($builder) => $builder->where('city', $request->string('city')));
        }

        if ($request->filled('status')) {
            $query->where('usage_status', $request->string('status'));
        }

        if ($request->filled('uses_min')) {
            $query->has('ivrCallRecords', '>=', (int) $request->integer('uses_min'));
        }

        if ($request->filled('uses_max')) {
            $query->has('ivrCallRecords', '<=', (int) $request->integer('uses_max'));
        }

        return $query->orderBy('usage_status')->orderByDesc('last_called_at');
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
}
