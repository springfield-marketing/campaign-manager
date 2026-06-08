<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientInteraction;
use App\Models\ClientPhoneNumber;
use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IvrNumberController extends Controller
{
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

    private function eligibleExportQuery(Request $request): Builder
    {
        $digitsSql = "replace(replace(replace(replace(replace(replace(coalesce(client_phone_numbers.normalized_phone, client_phone_numbers.raw_phone, ''), '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";

        $rankedNumbers = $this->filteredQuery($request)
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

    private function filteredQuery(Request $request): Builder
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

        if ($request->filled('tag')) {
            $query->whereHas('client.tags', fn ($builder) => $builder
                ->where('tags.name', $request->string('tag')));
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
}
