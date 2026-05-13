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

        try {
            // Default region AE so UAE local numbers (05x / 5x) parse correctly
            $parsed = $this->util->parse($input, 'AE');
        } catch (NumberParseException $e) {
            throw new \InvalidArgumentException("Cannot parse phone number \"{$input}\": {$e->getMessage()}");
        }

        if (! $this->util->isValidNumber($parsed)) {
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
