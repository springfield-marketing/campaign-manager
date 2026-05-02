<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContactSuppression;
use App\Modules\IVR\Jobs\ProcessUnsubscriberImport;
use App\Modules\IVR\Models\IvrImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IvrUnsubscriberController extends Controller
{
    private const UNSUBSCRIBE_REASONS = ['unsubscribe', 'customer_unsubscribed'];

    public function index(Request $request): View
    {
        $unsubscribers = ContactSuppression::query()
            ->with(['phoneNumber.client'])
            ->where('channel', 'ivr')
            ->whereIn('reason', self::UNSUBSCRIBE_REASONS)
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
            'imports' => IvrImport::query()
                ->where('type', 'unsubscribers')
                ->latest()
                ->paginate(10, ['*'], 'imports_page'),
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

        $storedPath = $validated['file']->store('ivr/imports/unsubscribers', 'local');

        $import = IvrImport::create([
            'type' => 'unsubscribers',
            'status' => 'pending',
            'original_file_name' => $validated['file']->getClientOriginalName(),
            'stored_file_name' => basename($storedPath),
            'storage_path' => $storedPath,
            'uploaded_by' => $request->user()?->id,
            'summary' => [
                'format' => 'phone,name',
                'created_rows' => 0,
                'existing_rows' => 0,
            ],
        ]);

        ProcessUnsubscriberImport::dispatch($import->id);

        return redirect()
            ->route('modules.ivr.unsubscribers.index')
            ->with('status', 'Unsubscriber import queued successfully.');
    }

    public function destroy(ContactSuppression $suppression): RedirectResponse
    {
        abort_unless(
            $suppression->channel === 'ivr' && in_array($suppression->reason, self::UNSUBSCRIBE_REASONS, true),
            404,
        );

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

    public function status(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $imports = IvrImport::query()
            ->where('type', 'unsubscribers')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (IvrImport $import): array => [
                'id' => $import->id,
                'status' => $import->status,
                'status_label' => $import->statusLabel(),
                'total_rows' => $import->total_rows,
                'processed_rows' => $import->processed_rows,
                'successful_rows' => $import->successful_rows,
                'failed_rows' => $import->failed_rows,
                'duplicate_rows' => $import->duplicate_rows,
                'progress' => $import->total_rows > 0
                    ? min(100, round(($import->processed_rows / $import->total_rows) * 100))
                    : 0,
                'is_active' => in_array($import->status, ['pending', 'processing'], true),
            ])
            ->values();

        return response()->json(['imports' => $imports]);
    }
}
