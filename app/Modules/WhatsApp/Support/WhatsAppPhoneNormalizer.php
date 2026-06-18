<?php

namespace App\Modules\WhatsApp\Support;

use App\Support\Identity\PhoneNormalizer;

/**
 * @deprecated Use {@see \App\Support\Identity\PhoneNormalizer} directly. Kept as a thin
 * delegate so existing WhatsApp call sites (DI-injected and `app(...)`-resolved) keep working
 * while we converge on the single canonical normalizer (contact-data spec §7, INV-2).
 *
 * The canonical normalizer is the same libphonenumber engine this class used, now also
 * rejecting spreadsheet scientific notation and obvious placeholder/fake numbers.
 */
class WhatsAppPhoneNormalizer
{
    private PhoneNormalizer $canonical;

    public function __construct(?PhoneNormalizer $canonical = null)
    {
        $this->canonical = $canonical ?? new PhoneNormalizer();
    }

    /**
     * @return array{normalized:string, country_code:string, national_number:string, detected_country:string, is_uae:bool}
     * @throws \InvalidArgumentException
     */
    public function normalize(string $value, bool $lenient = false): array
    {
        return $this->canonical->normalize($value, $lenient);
    }
}
