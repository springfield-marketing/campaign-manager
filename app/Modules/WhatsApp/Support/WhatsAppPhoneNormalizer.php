<?php

namespace App\Modules\WhatsApp\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
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

        $parsed          = null;
        $parsedCandidate = null; // Parsed but failed isValidNumber
        $firstError      = null;
        $secondError     = null;

        try {
            $candidate = $this->util->parse($input, 'AE');
            if ($this->util->isValidNumber($candidate)) {
                $parsed = $candidate;
            } else {
                $parsedCandidate = $candidate;
            }
        } catch (NumberParseException $e) {
            $firstError = $e;
        }

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
                    } else {
                        $parsedCandidate = $retried;
                    }
                } catch (NumberParseException $e) {
                    $secondError = $e;
                }
            }
        }

        if ($parsed === null) {
            throw new \InvalidArgumentException(
                $this->describeFailure($input, $firstError, $secondError, $parsedCandidate)
            );
        }

        $e164           = $this->util->format($parsed, PhoneNumberFormat::E164);
        $countryCode    = (string) $parsed->getCountryCode();
        $nationalNumber = (string) $parsed->getNationalNumber();
        $regionCode     = $this->util->getRegionCodeForNumber($parsed) ?? '';
        $isUae          = $regionCode === 'AE';

        return [
            'normalized'       => $e164,
            'country_code'     => $countryCode,
            'national_number'  => $nationalNumber,
            'detected_country' => $regionCode,
            'is_uae'           => $isUae,
        ];
    }

    private function describeFailure(
        string $input,
        ?NumberParseException $firstError,
        ?NumberParseException $secondError,
        mixed $parsedCandidate,
    ): string {
        $base = "Phone number \"{$input}\"";

        // Parsed successfully but failed libphonenumber's validity check — give country + type context
        if ($parsedCandidate !== null) {
            $region      = $this->util->getRegionCodeForNumber($parsedCandidate) ?? 'unknown';
            $cc          = $parsedCandidate->getCountryCode();
            $numberType  = $this->util->getNumberType($parsedCandidate);
            $typeLabel   = match ($numberType) {
                PhoneNumberType::FIXED_LINE          => 'fixed line / landline',
                PhoneNumberType::TOLL_FREE           => 'toll-free number',
                PhoneNumberType::PREMIUM_RATE        => 'premium-rate number',
                PhoneNumberType::VOIP                => 'VoIP number',
                PhoneNumberType::SHARED_COST         => 'shared-cost number',
                PhoneNumberType::PERSONAL_NUMBER     => 'personal number',
                PhoneNumberType::FIXED_LINE_OR_MOBILE => 'fixed line or mobile',
                default                              => 'unrecognised number type',
            };

            return "{$base}: parsed as {$typeLabel} for {$region} (+{$cc}) but did not pass validity check — "
                . "number may be incorrectly formatted or not assigned.";
        }

        // Could not parse at all — report the most specific error available
        $error = $secondError ?? $firstError;

        if ($error !== null) {
            $reason = match ($error->getErrorType()) {
                NumberParseException::INVALID_COUNTRY_CODE  => 'no valid country code found — prefix with the country code (e.g. +966 for Saudi Arabia, +44 for UK)',
                NumberParseException::NOT_A_NUMBER          => 'not a recognisable phone number format',
                NumberParseException::TOO_SHORT_AFTER_IDD   => 'too short after the international dialling prefix',
                NumberParseException::TOO_SHORT_NSN         => 'national number too short for the detected country',
                NumberParseException::TOO_LONG              => 'number too long ('.strlen(preg_replace('/\D/', '', $input)).' digits)',
                default                                     => 'could not be parsed',
            };

            return "{$base}: {$reason}.";
        }

        return "{$base}: not a valid number.";
    }
}
