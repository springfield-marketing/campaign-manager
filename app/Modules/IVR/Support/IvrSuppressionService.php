<?php

namespace App\Modules\IVR\Support;

use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class IvrSuppressionService
{
    public function __construct(
        private readonly NumberEligibilityService $eligibility,
    ) {}

    public function suppress(ClientPhoneNumber $record, ?string $reason = null): void
    {
        DB::transaction(function () use ($record, $reason): void {
            ContactSuppression::firstOrCreate(
                [
                    'client_phone_number_id' => $record->id,
                    'channel'                => 'ivr',
                    'reason'                 => 'customer_unsubscribed',
                ],
                [
                    'suppressed_at' => now(),
                    'context'       => ['source' => 'manual', 'reason' => $reason],
                ],
            );

            $record->forceFill(['unsubscribed_at' => $record->unsubscribed_at ?? now()])->save();
            $this->eligibility->refresh($record->refresh());
        });
    }

    public function unsuppress(ClientPhoneNumber $record): void
    {
        DB::transaction(function () use ($record): void {
            ContactSuppression::where('client_phone_number_id', $record->id)
                ->where('channel', 'ivr')
                ->where('reason', 'customer_unsubscribed')
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            if (! $record->suppressions()->activeIvr()->exists()) {
                $record->forceFill(['unsubscribed_at' => null])->save();
            }

            $this->eligibility->refresh($record->refresh());
        });
    }

    public function bulkSuppress(Collection $records): int
    {
        $ids = $records->pluck('id')->all();

        $alreadySuppressedIds = ContactSuppression::query()
            ->whereIn('client_phone_number_id', $ids)
            ->where('channel', 'ivr')
            ->whereNull('released_at')
            ->pluck('client_phone_number_id')
            ->all();

        $toSuppress = $records->reject(fn ($r) => in_array($r->id, $alreadySuppressedIds, true));

        $now = now();

        DB::transaction(function () use ($toSuppress, $now): void {
            foreach ($toSuppress as $record) {
                ContactSuppression::firstOrCreate(
                    [
                        'client_phone_number_id' => $record->id,
                        'channel'                => 'ivr',
                        'reason'                 => 'customer_unsubscribed',
                    ],
                    ['suppressed_at' => $now, 'context' => ['source' => 'manual_bulk']],
                );
                $record->forceFill(['unsubscribed_at' => $record->unsubscribed_at ?? $now])->save();
                $this->eligibility->refresh($record->refresh());
            }
        });

        return $toSuppress->count();
    }
}
