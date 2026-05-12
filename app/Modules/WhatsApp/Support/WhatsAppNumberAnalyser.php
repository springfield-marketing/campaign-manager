<?php

namespace App\Modules\WhatsApp\Support;

use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppPhoneProfile;

class WhatsAppNumberAnalyser
{
    public function analyse(int $phoneNumberId): void
    {
        $threshold = config('whatsapp.failure_threshold', 3);

        // Load all messages for this number newest-first so consecutive failure
        // count comes naturally from the top of the list.
        $messages = WhatsAppMessage::query()
            ->where('client_phone_number_id', $phoneNumberId)
            ->orderByDesc('scheduled_at')
            ->get(['delivery_status', 'has_quick_replies', 'quick_reply_1', 'quick_reply_2', 'quick_reply_3', 'scheduled_at']);

        if ($messages->isEmpty()) {
            return;
        }

        $isLead = false;
        $isSpam = false;
        $consecutiveFailed = 0;
        $countingFailures = true;

        foreach ($messages as $message) {
            // Consecutive failure count — stop counting once we hit a non-failure
            if ($countingFailures) {
                if ($message->delivery_status === 'FAILED') {
                    $consecutiveFailed++;
                } else {
                    $countingFailures = false;
                }
            }

            // Quick reply analysis — scan all messages
            if ($message->has_quick_replies) {
                if (! empty($message->quick_reply_3)) {
                    $isSpam = true;
                } elseif (! empty($message->quick_reply_1) || ! empty($message->quick_reply_2)) {
                    $isLead = true;
                }
            }
        }

        $latest = $messages->first();

        // Update or create the phone profile
        WhatsAppPhoneProfile::updateOrCreate(
            ['client_phone_number_id' => $phoneNumberId],
            [
                'consecutive_failed_count' => $consecutiveFailed,
                'last_message_status' => $latest->delivery_status,
                'last_messaged_at' => $latest->scheduled_at,
            ],
        );

        // Spam / reported — suppress immediately regardless of lead status
        if ($isSpam) {
            ContactSuppression::firstOrCreate(
                [
                    'client_phone_number_id' => $phoneNumberId,
                    'channel' => 'whatsapp',
                    'reason' => 'reported_spam',
                ],
                [
                    'context' => [],
                    'suppressed_at' => now(),
                ],
            );
        }

        // Consecutive failures — suppress and clear the lead flag if set
        if ($consecutiveFailed >= $threshold) {
            ContactSuppression::firstOrCreate(
                [
                    'client_phone_number_id' => $phoneNumberId,
                    'channel' => 'whatsapp',
                    'reason' => 'repeated_failures',
                ],
                [
                    'context' => ['consecutive_failed_count' => $consecutiveFailed],
                    'suppressed_at' => now(),
                ],
            );

            // A number that keeps failing is not a usable lead
            ClientPhoneNumber::where('id', $phoneNumberId)
                ->update(['is_whatsapp_lead' => false]);

            return;
        }

        // Mark as lead only if not spam and not suppressed by failures
        if ($isLead && ! $isSpam) {
            ClientPhoneNumber::where('id', $phoneNumberId)
                ->update(['is_whatsapp_lead' => true]);
        }
    }
}
