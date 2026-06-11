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
        $inactiveAfterConsecutiveNoAnswers = (int) config('ivr.eligibility.inactive_after_consecutive_no_answers', 5);

        // One query for the latest calls needed to determine cooldown and no-answer streaks.
        $recentRecords = $phoneNumber->ivrCallRecords()
            ->latest('call_time')
            ->limit($inactiveAfterConsecutiveNoAnswers)
            ->get(['call_time', 'call_status', 'dtmf_outcome']);

        $lastCall = $recentRecords->first();
        $recentStatuses = $recentRecords->pluck('call_status');

        $cooldownUntil = null;

        if ($lastCall && $lastCall->call_time instanceof CarbonInterface) {
            $cooldownUntil = strcasecmp((string) $lastCall->call_status, 'Answered') === 0
                ? $lastCall->call_time->copy()->addDays($this->settings->cooldown_answered_days)
                : $lastCall->call_time->copy()->addDays($this->settings->cooldown_missed_days);
        }

        $consecutiveMisses = 0;
        foreach ($recentStatuses as $callStatus) {
            if (strcasecmp((string) $callStatus, 'Answered') !== 0) {
                $consecutiveMisses++;
            } else {
                break;
            }
        }

        $status = 'active';

        if (
            $consecutiveMisses >= $inactiveAfterConsecutiveNoAnswers
            || ($cooldownUntil && now()->lt($cooldownUntil))
        ) {
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
