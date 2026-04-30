<?php

namespace App\Modules\IVR\Support;

use App\Models\ClientPhoneNumber;
use Carbon\CarbonInterface;

class NumberEligibilityService
{
    public function refresh(ClientPhoneNumber $phoneNumber): void
    {
        $lastCall = $phoneNumber->ivrCallRecords()->latest('call_time')->first();
        $useCount = $phoneNumber->ivrCallRecords()->count();

        $cooldownUntil = null;

        if ($lastCall && $lastCall->call_time instanceof CarbonInterface) {
            $cooldownUntil = strcasecmp((string) $lastCall->call_status, 'Answered') === 0
                ? $lastCall->call_time->copy()->addDays((int) config('ivr.cooldowns.answered_days', 45))
                : $lastCall->call_time->copy()->addDays((int) config('ivr.cooldowns.missed_days', 1));
        }

        $isSuppressed = $phoneNumber->unsubscribed_at !== null;
        $inactiveAfterUses = (int) config('ivr.eligibility.inactive_after_uses', 3);
        $deadAfterUses = (int) config('ivr.eligibility.dead_after_uses', 5);

        $status = 'active';

        if ($isSuppressed || $useCount >= $deadAfterUses) {
            $status = 'dead';
        } elseif (($cooldownUntil && now()->lt($cooldownUntil)) || $useCount >= $inactiveAfterUses) {
            $status = 'inactive';
        }

        $phoneNumber->forceFill([
            'usage_status' => $status,
            'last_call_outcome' => $lastCall?->dtmf_outcome,
            'last_called_at' => $lastCall?->call_time,
            'cooldown_until' => $cooldownUntil,
        ])->save();
    }
}
