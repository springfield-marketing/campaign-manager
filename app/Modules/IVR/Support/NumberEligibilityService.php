<?php

namespace App\Modules\IVR\Support;

use App\Models\ClientPhoneNumber;
use App\Modules\IVR\Models\IvrPhoneProfile;
use App\Modules\IVR\Models\IvrSettings;
use Carbon\CarbonInterface;

class NumberEligibilityService
{
    private IvrSettings $settings;

    public function __construct()
    {
        $this->settings = IvrSettings::current();
    }

    public function refresh(ClientPhoneNumber $phoneNumber): void
    {
        $lastCall = $phoneNumber->ivrCallRecords()->latest('call_time')->first();
        $useCount = $phoneNumber->ivrCallRecords()->count();

        $cooldownUntil = null;

        if ($lastCall && $lastCall->call_time instanceof CarbonInterface) {
            $cooldownUntil = strcasecmp((string) $lastCall->call_status, 'Answered') === 0
                ? $lastCall->call_time->copy()->addDays($this->settings->cooldown_answered_days)
                : $lastCall->call_time->copy()->addDays($this->settings->cooldown_missed_days);
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

        IvrPhoneProfile::updateOrCreate(
            ['client_phone_number_id' => $phoneNumber->id],
            [
                'usage_status' => $status,
                'last_call_outcome' => $lastCall?->dtmf_outcome,
                'last_called_at' => $lastCall?->call_time,
                'cooldown_until' => $cooldownUntil,
            ]
        );
    }
}
