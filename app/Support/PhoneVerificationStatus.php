<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Keeps client_phone_numbers.verification_status self-maintaining from real campaign
 * outcomes, so callable-number quality improves automatically as imports land.
 *
 * Only ever promotes a number to "verified" — when it provably reached a person. We do not
 * flip numbers to "invalid" from delivery failures: WhatsApp "FAILED" is dominated by
 * sender-side causes (Meta quality throttling, insufficient credit, paused templates,
 * experiments) rather than the number being bad, so treating failures as invalid would
 * wrongly condemn real contacts. "invalid" is reserved for numbers that are structurally
 * placeholder-shaped and have never shown a sign of life (handled at classification time),
 * and the phone format CHECK constraint already blocks placeholder numbers from entering
 * as "unverified" in the first place.
 */
class PhoneVerificationStatus
{
    /** WhatsApp delivery states that prove the message reached a real device. */
    public const WHATSAPP_REACHED = ['DELIVERED', 'READ', 'REPLIED', 'STOPPED'];

    /**
     * Promote a single number to verified once an IVR call actually connected.
     */
    public static function recordIvrOutcome(int $phoneNumberId, ?string $callStatus): void
    {
        if ($callStatus !== 'Answered') {
            return;
        }

        DB::table('client_phone_numbers')
            ->where('id', $phoneNumberId)
            ->where('verification_status', '<>', 'verified')
            ->update(['verification_status' => 'verified', 'updated_at' => now()]);
    }

    /**
     * Promote, in one set-based statement, every number from a WhatsApp import that reached
     * a device. WhatsApp messages are bulk-inserted, so this runs once after the insert
     * rather than per row.
     *
     * @param  array<int>  $phoneNumberIds  the unique numbers touched by this import
     */
    public static function recordWhatsAppImport(int $importId, array $phoneNumberIds): void
    {
        if ($phoneNumberIds === []) {
            return;
        }

        DB::table('client_phone_numbers')
            ->whereIn('id', $phoneNumberIds)
            ->where('verification_status', '<>', 'verified')
            ->whereExists(function ($query) use ($importId): void {
                $query->selectRaw('1')
                    ->from('whatsapp_messages')
                    ->whereColumn('whatsapp_messages.client_phone_number_id', 'client_phone_numbers.id')
                    ->where('whatsapp_messages.whatsapp_import_id', $importId)
                    ->whereIn('whatsapp_messages.delivery_status', self::WHATSAPP_REACHED);
            })
            ->update(['verification_status' => 'verified', 'updated_at' => now()]);
    }
}
