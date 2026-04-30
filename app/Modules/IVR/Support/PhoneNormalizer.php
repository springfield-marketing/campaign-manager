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
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            throw new \InvalidArgumentException('Phone number is empty after normalization.');
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '971') && strlen($digits) >= 11 && strlen($digits) <= 12) {
            $national = substr($digits, 3);

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
}
