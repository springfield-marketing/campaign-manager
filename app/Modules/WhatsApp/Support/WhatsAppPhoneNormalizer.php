<?php

namespace App\Modules\WhatsApp\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class WhatsAppPhoneNormalizer
{
    private PhoneNumberUtil $util;

    public function __construct()
    {
        $this->util = PhoneNumberUtil::getInstance();
    }

    /**
     * Parse and validate any phone number.
     * UAE local formats (05x, 5x, 971x) are detected without a leading +.
     * All other numbers must include a country code (e.g. +44, +1).
     *
     * @return array{normalized:string, country_code:string, national_number:string, detected_country:string, is_uae:bool}
     * @throws \InvalidArgumentException
     */
    public function normalize(string $value): array
    {
        $input = trim($value);

        if ($input === '') {
            throw new \InvalidArgumentException('Phone number is empty.');
        }

        $parsed = null;

        try {
            // Default region AE so UAE local numbers (05x / 5x) parse without a country code
            $candidate = $this->util->parse($input, 'AE');
            if ($this->util->isValidNumber($candidate)) {
                $parsed = $candidate;
            }
        } catch (NumberParseException $e) {
            // fall through to retry
        }

        // Numbers stored without a leading + (e.g. 966501234567, 447911234567) are not
        // recognised by the AE-region parse. Prepend + and retry using no default region
        // so libphonenumber reads the country code directly from the digits.
        if ($parsed === null
            && ! str_starts_with($input, '+')
            && ! str_starts_with($input, '00')
        ) {
            $digitsOnly = preg_replace('/\D/', '', $input);
            if (strlen($digitsOnly) >= 10) {
                try {
                    $retried = $this->util->parse('+' . $digitsOnly, 'ZZ');
                    if ($this->util->isValidNumber($retried)) {
                        $parsed = $retried;
                    }
                } catch (NumberParseException $e) {
                    // ignore — original error thrown below
                }
            }
        }

        if ($parsed === null) {
            throw new \InvalidArgumentException("Phone number \"{$input}\" is not a valid number.");
        }

        $e164          = $this->util->format($parsed, PhoneNumberFormat::E164);
        $countryCode   = (string) $parsed->getCountryCode();
        $nationalNumber = (string) $parsed->getNationalNumber();
        $regionCode    = $this->util->getRegionCodeForNumber($parsed) ?? '';
        $isUae         = $regionCode === 'AE';

        return [
            'normalized'      => $e164,
            'country_code'    => $countryCode,
            'national_number' => $nationalNumber,
            'detected_country' => $regionCode,
            'is_uae'          => $isUae,
        ];
    }
}
