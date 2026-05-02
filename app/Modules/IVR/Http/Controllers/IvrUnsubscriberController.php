<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\IVR\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use SplFileObject;
use Throwable;

class IvrUnsubscriberController extends Controller
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {
    }

    public function index(Request $request): View
    {
        $unsubscribers = ContactSuppression::query()
            ->with(['phoneNumber.client'])
            ->where('channel', 'ivr')
            ->where('reason', 'unsubscribe')
            ->whereNull('released_at')
            ->when($request->filled('phone'), function (Builder $query) use ($request): void {
                $phone = trim((string) $request->input('phone'));
                $digits = preg_replace('/\D+/', '', $phone) ?: null;

                $query->whereHas('phoneNumber', function (Builder $query) use ($phone, $digits): void {
                    $query->where('normalized_phone', 'like', '%'.$phone.'%')
                        ->orWhere('raw_phone', 'like', '%'.$phone.'%');

                    if ($digits) {
                        $query->orWhere('normalized_phone', 'like', '%'.$digits.'%')
                            ->orWhere('raw_phone', 'like', '%'.$digits.'%')
                            ->orWhere('national_number', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->when($request->filled('name'), function (Builder $query) use ($request): void {
                $name = trim((string) $request->input('name'));

                $query->whereHas('phoneNumber.client', fn (Builder $query) => $query
                    ->where('full_name', 'like', '%'.$name.'%'));
            })
            ->latest('suppressed_at')
            ->paginate(25)
            ->withQueryString();

        return view('ivr::unsubscribers.index', [
            'unsubscribers' => $unsubscribers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            ],
            [
                'file.uploaded' => 'The file could not be uploaded because it is larger than the current PHP upload limit.',
                'file.max' => 'The file must be 10 MB or smaller.',
            ],
        );

        $file = new SplFileObject($validated['file']->getRealPath());
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '\\');

        $processed = 0;
        $created = 0;
        $existing = 0;
        $failed = 0;
        $rowNumber = 0;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            $rowNumber++;

            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }

            if ($rowNumber === 1 && $this->looksLikeHeader($row)) {
                continue;
            }

            $processed++;

            try {
                $wasCreated = $this->upsertUnsubscriber(
                    phone: trim((string) ($row[0] ?? '')),
                    name: trim((string) ($row[1] ?? '')) ?: null,
                    sourceFile: $validated['file']->getClientOriginalName(),
                    rowNumber: $rowNumber,
                );

                $wasCreated ? $created++ : $existing++;
            } catch (Throwable) {
                $failed++;
            }
        }

        return redirect()
            ->route('modules.ivr.unsubscribers.index')
            ->with('status', "Unsubscriber import complete. {$created} added, {$existing} already existed, {$failed} failed.");
    }

    public function destroy(ContactSuppression $suppression): RedirectResponse
    {
        abort_unless($suppression->channel === 'ivr' && $suppression->reason === 'unsubscribe', 404);

        DB::transaction(function () use ($suppression): void {
            $phoneNumber = $suppression->phoneNumber;

            $suppression->forceFill([
                'released_at' => now(),
            ])->save();

            if ($phoneNumber && $phoneNumber->suppressions()
                ->whereNull('released_at')
                ->where(function (Builder $query): void {
                    $query->whereNull('channel')
                        ->orWhere('channel', 'ivr');
                })
                ->doesntExist()) {
                $phoneNumber->forceFill([
                    'unsubscribed_at' => null,
                ])->save();
            }
        });

        return back()->with('status', 'Unsubscriber removed.');
    }

    private function upsertUnsubscriber(string $phone, ?string $name, string $sourceFile, int $rowNumber): bool
    {
        if ($phone === '') {
            throw new \RuntimeException('Phone number is required.');
        }

        $normalized = $this->phoneNormalizer->normalize($phone);

        return DB::transaction(function () use ($phone, $name, $sourceFile, $rowNumber, $normalized): bool {
            $phoneNumber = ClientPhoneNumber::query()
                ->where('normalized_phone', $normalized['normalized'])
                ->first();

            if (! $phoneNumber) {
                $client = Client::create([
                    'full_name' => $name,
                ]);

                $phoneNumber = ClientPhoneNumber::create([
                    'client_id' => $client->id,
                    'raw_phone' => $phone,
                    'normalized_phone' => $normalized['normalized'],
                    'country_code' => $normalized['country_code'],
                    'national_number' => $normalized['national_number'],
                    'detected_country' => $normalized['detected_country'],
                    'is_uae' => $normalized['is_uae'],
                    'is_primary' => true,
                    'priority' => 1,
                    'usage_status' => 'active',
                    'unsubscribed_at' => now(),
                ]);
            } else {
                $client = $phoneNumber->client ?: Client::create(['full_name' => $name]);

                if ($name && trim((string) $client->full_name) === '') {
                    $client->forceFill(['full_name' => $name])->save();
                }

                $phoneNumber->forceFill([
                    'client_id' => $client->id,
                    'raw_phone' => $phone,
                    'unsubscribed_at' => $phoneNumber->unsubscribed_at ?: now(),
                ])->save();
            }

            $existing = ContactSuppression::query()
                ->where('client_phone_number_id', $phoneNumber->id)
                ->where('channel', 'ivr')
                ->where('reason', 'unsubscribe')
                ->whereNull('released_at')
                ->first();

            if ($existing) {
                return false;
            }

            ContactSuppression::create([
                'client_phone_number_id' => $phoneNumber->id,
                'channel' => 'ivr',
                'reason' => 'unsubscribe',
                'context' => [
                    'source' => 'unsubscriber_import',
                    'source_file' => $sourceFile,
                    'row_number' => $rowNumber,
                    'name' => $name,
                ],
                'suppressed_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function looksLikeHeader(array $row): bool
    {
        return str_contains(strtolower((string) ($row[0] ?? '')), 'phone')
            || str_contains(strtolower((string) ($row[1] ?? '')), 'name');
    }
}
