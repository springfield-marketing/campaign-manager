<?php

namespace App\Modules\IVR\Support;

class PhoneNormalizer
{
    /**
     * @return array{normalized:string, country_code:?string, national_number:?string, detected_country:?string, is_uae:bool}
     */
    public function normalize(string $value): array
    {
        $trimmed = trim($value);

        if (preg_match('/^[\d.]+E[+-]?\d+$/i', $trimmed)) {
            throw new \InvalidArgumentException(
                "Phone number \"{$trimmed}\" looks like spreadsheet scientific notation (e.g. a long number ".
                'that Excel/Sheets rendered as "9.71E+11") rather than a real phone number.'
            );
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            throw new \InvalidArgumentException('Phone number is empty after normalization.');
        }

        if (strlen($digits) < 9 || strlen($digits) > 15) {
            throw new \InvalidArgumentException(
                "Phone number \"{$trimmed}\" has {$digits} digits after stripping formatting — too short or too long to be valid."
            );
        }

        if ($this->looksLikePlaceholder($digits)) {
            throw new \InvalidArgumentException("Phone number \"{$trimmed}\" looks like a placeholder/fake number, not a real one.");
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '971') && strlen($digits) >= 11 && strlen($digits) <= 12) {
            $national = $this->resolveUaeNationalNumber(substr($digits, 3), $trimmed);

            return [
                'normalized' => '+971'.$national,
                'country_code' => '971',
                'national_number' => $national,
                'detected_country' => 'UAE',
                'is_uae' => true,
            ];
        }

        if (str_starts_with($digits, '05') && strlen($digits) === 10) {
            $national = substr($digits, 1);

            return [
                'normalized' => '+971'.$national,
                'country_code' => '971',
                'national_number' => $national,
                'detected_country' => 'UAE',
                'is_uae' => true,
            ];
        }

        if (str_starts_with($digits, '5') && strlen($digits) === 9) {
            return [
                'normalized' => '+971'.$digits,
                'country_code' => '971',
                'national_number' => $digits,
                'detected_country' => 'UAE',
                'is_uae' => true,
            ];
        }

        $countryCode = strlen($digits) > 10 ? substr($digits, 0, 3) : null;

        return [
            'normalized' => '+'.$digits,
            'country_code' => $countryCode,
            'national_number' => $countryCode ? substr($digits, strlen($countryCode)) : $digits,
            'detected_country' => null,
            'is_uae' => false,
        ];
    }

    /**
     * UAE mobile numbers are 9 digits starting with 5 (e.g. 501234567). Landline numbers are
     * 8 digits starting with the area code 2, 3, 4, 6, 7, or 9 (e.g. 43905067 for Dubai).
     * A common data-entry mistake leaks the local dialling "0" through (e.g. dialling "04 390
     * 5067" domestically becomes "971" + "0" + "43905067" instead of "971" + "43905067") — we
     * recover that case by stripping a single leading 0 when what's left is a valid landline.
     */
    private function resolveUaeNationalNumber(string $national, string $original): string
    {
        if (strlen($national) === 9 && $national[0] === '5') {
            return $national;
        }

        if (strlen($national) === 8 && in_array($national[0], ['2', '3', '4', '6', '7', '9'], true)) {
            return $national;
        }

        if (strlen($national) === 9 && $national[0] === '0') {
            $stripped = substr($national, 1);

            if (in_array($stripped[0], ['2', '3', '4', '6', '7', '9'], true)) {
                return $stripped;
            }
        }

        throw new \InvalidArgumentException(
            "Phone number \"{$original}\" doesn't match a valid UAE mobile (5xxxxxxxx) or landline (2/3/4/6/7/9xxxxxxx) prefix."
        );
    }

    public function looksLikePlaceholder(string $digits): bool
    {
        // Check the trailing "local number" portion rather than the full string, since a
        // legitimate country code (e.g. 971) adds digit variety that would mask a fake local number.
        $local = substr($digits, -9);

        if (preg_match('/0{6,}$/', $local)) {
            return true;
        }

        $counts = array_count_values(str_split($local));

        if (max($counts) >= strlen($local) - 2) {
            return true;
        }

        // Catches classic example/placeholder numbers ending in a long run like "...1234567"
        // or "...7654321". Anchored to the end and requiring 7+ digits avoids false-positiving
        // on real numbers that merely contain a short coincidental run (e.g. "...524567870").
        for ($length = strlen($local); $length >= 7; $length--) {
            $tail = substr($local, -$length);

            if (str_contains('0123456789', $tail) || str_contains('9876543210', $tail)) {
                return true;
            }
        }

        return false;
    }
}
