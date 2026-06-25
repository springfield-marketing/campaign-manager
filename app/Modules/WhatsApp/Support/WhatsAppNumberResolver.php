<?php

namespace App\Modules\WhatsApp\Support;

use App\Models\ClientPhoneNumber;
use Throwable;

/**
 * Finds the existing phone number a Do-Not-Message entry refers to.
 *
 * Unsubscribe/DNC inputs come from already-run campaigns, so the number is on file — we want to
 * suppress THAT record, not re-validate the input. This tolerates legacy formats that no longer
 * pass libphonenumber (e.g. Mexico dropped the mobile "1" prefix in 2019, so "5214434630828" is
 * now "invalid" even though the real, campaigned record is "+524434630828").
 */
class WhatsAppNumberResolver
{
    public function __construct(
        private readonly WhatsAppPhoneNormalizer $normalizer,
    ) {}

    /**
     * The matched ClientPhoneNumber, or null if nothing on file corresponds to the input.
     */
    public function resolveExisting(string $raw): ?ClientPhoneNumber
    {
        // 1. Exact match on the canonical normalized form (lenient = accept routable-but-nonstandard).
        try {
            $normalized = $this->normalizer->normalize($raw, lenient: true)['normalized'];

            if ($match = ClientPhoneNumber::where('normalized_phone', $normalized)->first()) {
                return $match;
            }
        } catch (Throwable) {
            // Couldn't normalize (legacy/invalid format) — fall through to suffix matching.
        }

        // 2. Legacy-format fallback: match the longest trailing run of digits against an existing
        //    national_number. Longest-first + uniqueness avoids ever suppressing the wrong number.
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        foreach ([12, 11, 10, 9] as $length) {
            if (strlen($digits) < $length) {
                continue;
            }

            $matches = ClientPhoneNumber::query()
                ->where('national_number', substr($digits, -$length))
                ->limit(2)
                ->get();

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }
}
