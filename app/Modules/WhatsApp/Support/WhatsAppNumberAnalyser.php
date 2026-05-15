<?php

namespace App\Modules\WhatsApp\Support;

use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppPhoneProfile;
use Illuminate\Support\Carbon;

class WhatsAppNumberAnalyser
{
    // Failure reason classification — matched via str_contains, order matters.
    // Each entry: [substring to match, category]
    private const FAILURE_PATTERNS = [
        ['chosen to stop receiving marketing messages', 'opted_out'],
        ['retry again in a few days',                  'quality_hold'],
        ['part of an experiment',                      'experiment'],
        ['US recipients',                              'regional'],
        ['Insufficient credit balance',                'system_error'],
        ['Something went wrong',                       'system_error'],
        ['OAuthException',                             'system_error'],
    ];
    // Anything else that is FAILED falls through to 'hard_fail'

    public function analyse(int $phoneNumberId): void
    {
        $messages = WhatsAppMessage::query()
            ->where('client_phone_number_id', $phoneNumberId)
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get(['delivery_status', 'failure_reason', 'has_quick_replies',
                   'quick_reply_1', 'quick_reply_2', 'quick_reply_3', 'scheduled_at']);

        if ($messages->isEmpty()) {
            return;
        }

        $isLead              = false;
        $isSpam              = false;
        $consecutiveHardFails = 0;
        $totalMessages       = $messages->count();
        $totalHardFails      = 0;
        $totalNonSystemFails = 0;
        $countingConsecutive = true;
        $hasOptOut           = false;
        $latestCooldownUntil = null;

        foreach ($messages as $message) {
            $class = $message->delivery_status === 'FAILED'
                ? $this->classifyFailure((string) $message->failure_reason)
                : null;

            // Consecutive hard-fail streak — iterate newest-first.
            // System errors are transparent (don't count, don't break the streak).
            // Everything else (delivered, read, temp holds, opt-outs) ends the streak.
            if ($countingConsecutive) {
                if ($class === 'hard_fail') {
                    $consecutiveHardFails++;
                } elseif ($class !== 'system_error') {
                    $countingConsecutive = false;
                }
            }

            // Lifetime hard-fail and non-system-fail totals
            if ($class === 'hard_fail') {
                $totalHardFails++;
            }
            if ($class !== null && $class !== 'system_error') {
                $totalNonSystemFails++;
            }

            // Opt-out detection
            if ($class === 'opted_out') {
                $hasOptOut = true;
            }

            // Track the most recent cooldown-triggering failure (newest-first so first hit wins)
            if ($latestCooldownUntil === null && in_array($class, ['quality_hold', 'experiment', 'regional'], true)) {
                $latestCooldownUntil = $this->cooldownUntil($class, $message->scheduled_at);
            }

            // Quick-reply analysis
            if ($message->has_quick_replies) {
                if (! empty($message->quick_reply_3)) {
                    $isSpam = true;
                } elseif (! empty($message->quick_reply_1) || ! empty($message->quick_reply_2)) {
                    $isLead = true;
                }
            }
        }

        $latest            = $messages->first();
        $hardFailThreshold = config('whatsapp.hard_fail_threshold', 3);
        $bulkDeadThreshold = config('whatsapp.bulk_dead_threshold', 10);

        // Dead when consecutive hard fails exceed threshold,
        // OR the number has been tried 10+ times and every non-system attempt hard-failed.
        $isDead = $consecutiveHardFails >= $hardFailThreshold
            || ($totalMessages >= $bulkDeadThreshold
                && $totalNonSystemFails > 0
                && $totalHardFails === $totalNonSystemFails);

        // Determine usage_status (dead beats cooldown beats active)
        $usageStatus  = 'active';
        $cooldownUntil = null;

        if ($isDead) {
            $usageStatus = 'dead';
        } elseif ($latestCooldownUntil !== null && $latestCooldownUntil->isFuture()) {
            $usageStatus  = 'cooldown';
            $cooldownUntil = $latestCooldownUntil;
        }

        // Persist the profile
        WhatsAppPhoneProfile::updateOrCreate(
            ['client_phone_number_id' => $phoneNumberId],
            [
                'consecutive_hard_fail_count' => $consecutiveHardFails,
                'last_message_status'         => $latest->delivery_status,
                'last_failure_reason'         => $latest->failure_reason,
                'last_messaged_at'            => $latest->scheduled_at,
                'usage_status'                => $usageStatus,
                'cooldown_until'              => $cooldownUntil,
            ],
        );

        // Permanent opt-out → ContactSuppression (like an unsubscribe)
        if ($hasOptOut) {
            ContactSuppression::firstOrCreate(
                [
                    'client_phone_number_id' => $phoneNumberId,
                    'channel'                => 'whatsapp',
                    'reason'                 => 'opted_out',
                ],
                ['context' => [], 'suppressed_at' => now()],
            );
        }

        // Spam → suppress immediately
        if ($isSpam) {
            ContactSuppression::firstOrCreate(
                [
                    'client_phone_number_id' => $phoneNumberId,
                    'channel'                => 'whatsapp',
                    'reason'                 => 'reported_spam',
                ],
                ['context' => [], 'suppressed_at' => now()],
            );
        }

        // Dead numbers cannot be leads
        if ($isDead || $isSpam) {
            ClientPhoneNumber::where('id', $phoneNumberId)
                ->update(['is_whatsapp_lead' => false]);

            return;
        }

        if ($isLead) {
            ClientPhoneNumber::where('id', $phoneNumberId)
                ->update(['is_whatsapp_lead' => true]);
        }
    }

    private function classifyFailure(string $reason): string
    {
        foreach (self::FAILURE_PATTERNS as [$needle, $category]) {
            if (str_contains($reason, $needle)) {
                return $category;
            }
        }

        return 'hard_fail';
    }

    private function cooldownUntil(string $class, ?Carbon $from): Carbon
    {
        $days = config("whatsapp.cooldown_days.{$class}", 3);

        return ($from ?? now())->copy()->addDays($days);
    }
}
