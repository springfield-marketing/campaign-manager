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
                'sources' => fn ($query) => $query->latest(),
                'ivrCallRecords' => fn ($query) => $query->with('campaign')->latest('call_time'),
                'suppressions' => fn ($query) => $query->latest('suppressed_at'),
            ]),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $limit = $this->exportLimit($request);

        $numbers = $this->filteredQuery($request)
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

    private function filteredQuery(Request $request): Builder
    {
        $query = ClientPhoneNumber::query()
            ->where('is_uae', true)
            ->withCount(['ivrCallRecords as ivr_use_count']);

        $includeSources = $this->selectedSources($request, 'source_include');
        $excludeSources = $this->selectedSources($request, 'source_exclude');

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
            $query->having('ivr_use_count', '>=', (int) $request->integer('uses_min'));
        }

        if ($request->filled('uses_max')) {
            $query->having('ivr_use_count', '<=', (int) $request->integer('uses_max'));
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
