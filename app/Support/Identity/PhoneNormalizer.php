<?php

namespace App\Support\Identity;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * The ONE canonical phone normalizer (docs/data-rules/contact-data-spec.md §7, INV-2).
 *
 * Produces the canonical `+E.164` form that is the identity anchor (the unique
 * `client_phone_numbers.normalized_phone` index) and is matched against everywhere. Having a
 * single implementation is the point: two divergent normalizers can map the same input to
 * different canonical forms, which silently splits or leaks identity across channels.
 *
 * Built on libphonenumber (correct for every country) plus the two guards the old hand-rolled
 * IVR normalizer carried and libphonenumber does not: rejecting spreadsheet scientific notation
 * and obvious placeholder/fake numbers.
 *
 * Return shape is unchanged from the normalizers it replaces:
 *   ['normalized', 'country_code', 'national_number', 'detected_country', 'is_uae']
 * `detected_country` is the ISO-3166 region code (e.g. 'AE', 'GB') or '' when unknown.
 */
class PhoneNormalizer
{
    private PhoneNumberUtil $util;

    public function __construct()
    {
        $this->util = PhoneNumberUtil::getInstance();
    }

    /**
     * @param  bool  $lenient  When true, non-UAE numbers that parse but fail isValidNumber()
     *                         are accepted if they pass isPossibleNumber(). UAE numbers are
     *                         always validated strictly.
     *
     * @return array{normalized:string, country_code:string, national_number:string, detected_country:string, is_uae:bool}
     * @throws \InvalidArgumentException
     */
    public function normalize(string $value, bool $lenient = false): array
    {
        $input = trim($value);

        if ($input === '') {
            throw new \InvalidArgumentException('Phone number is empty.');
        }

        // Guard 1: spreadsheet scientific notation (e.g. "9.71E+11") is never a real number.
        if (preg_match('/^[\d.]+E[+-]?\d+$/i', $input)) {
            throw new \InvalidArgumentException(
                "Phone number \"{$input}\" looks like spreadsheet scientific notation (e.g. a long ".
                'number that Excel/Sheets rendered as "9.71E+11") rather than a real phone number.'
            );
        }

        // Guard 2: obvious placeholder/fake numbers (trailing-zero runs, single repeated digit,
        // sequential runs like ...1234567).
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if ($digits !== '' && $this->looksLikePlaceholder($digits)) {
            throw new \InvalidArgumentException("Phone number \"{$input}\" looks like a placeholder/fake number, not a real one.");
        }

        // Recover a leaked domestic-dialling "0" on a UAE landline: dialling "04 390 5067"
        // domestically can arrive as "971" + "0" + "43905067" (12 digits, 0 after the 971).
        // A real UAE mobile is "971" + "5xxxxxxxx", so a 0 in that slot is the leaked prefix.
        $input = $this->stripLeakedUaeLandlineZero($input, $digits);

        $parsed          = null;
        $parsedCandidate = null;
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

        // Lenient fallback: accept non-UAE numbers that parsed cleanly and pass
        // isPossibleNumber() even though isValidNumber() returned false. UAE stays strict.
        if ($parsed === null && $lenient && $parsedCandidate !== null) {
            $region = $this->util->getRegionCodeForNumber($parsedCandidate) ?? '';
            if ($region !== 'AE' && $this->util->isPossibleNumber($parsedCandidate)) {
                $parsed = $parsedCandidate;
            }
        }

        if ($parsed === null) {
            throw new \InvalidArgumentException(
                $this->describeFailure($input, $firstError, $secondError, $parsedCandidate)
            );
        }

        $regionCode = $this->util->getRegionCodeForNumber($parsed) ?? '';

        return [
            'normalized'       => $this->util->format($parsed, PhoneNumberFormat::E164),
            'country_code'     => (string) $parsed->getCountryCode(),
            'national_number'  => (string) $parsed->getNationalNumber(),
            'detected_country' => $regionCode,
            'is_uae'           => $regionCode === 'AE',
        ];
    }

    private function stripLeakedUaeLandlineZero(string $input, string $digits): string
    {
        // 12 digits, "971" + "0" + 8-digit landline whose first digit is a valid area code.
        if (strlen($digits) === 12 && str_starts_with($digits, '9710')) {
            $landline = substr($digits, 4); // 8 digits
            if (strlen($landline) === 8 && in_array($landline[0], ['2', '3', '4', '6', '7', '9'], true)) {
                return '+971' . $landline;
            }
        }

        return $input;
    }

    public function looksLikePlaceholder(string $digits): bool
    {
        // Inspect the trailing "local number" portion: a legitimate country code adds digit
        // variety that would otherwise mask a fake local number.
        $local = substr($digits, -9);

        if (preg_match('/0{6,}$/', $local)) {
            return true;
        }

        $counts = array_count_values(str_split($local));
        if (max($counts) >= strlen($local) - 2) {
            return true;
        }

        // Classic example numbers ending in a long sequential run ("...1234567" / "...7654321").
        for ($length = strlen($local); $length >= 7; $length--) {
            $tail = substr($local, -$length);
            if (str_contains('0123456789', $tail) || str_contains('9876543210', $tail)) {
                return true;
            }
        }

        return false;
    }

    private function describeFailure(
        string $input,
        ?NumberParseException $firstError,
        ?NumberParseException $secondError,
        mixed $parsedCandidate,
    ): string {
        $base = "Phone number \"{$input}\"";

        if ($parsedCandidate !== null) {
            $region     = $this->util->getRegionCodeForNumber($parsedCandidate) ?? 'unknown';
            $cc         = $parsedCandidate->getCountryCode();
            $typeLabel  = match ($this->util->getNumberType($parsedCandidate)) {
                PhoneNumberType::FIXED_LINE            => 'fixed line / landline',
                PhoneNumberType::TOLL_FREE             => 'toll-free number',
                PhoneNumberType::PREMIUM_RATE          => 'premium-rate number',
                PhoneNumberType::VOIP                  => 'VoIP number',
                PhoneNumberType::SHARED_COST           => 'shared-cost number',
                PhoneNumberType::PERSONAL_NUMBER       => 'personal number',
                PhoneNumberType::FIXED_LINE_OR_MOBILE  => 'fixed line or mobile',
                default                                => 'unrecognised number type',
            };

            return "{$base}: parsed as {$typeLabel} for {$region} (+{$cc}) but did not pass validity check — "
                . 'number may be incorrectly formatted or not assigned.';
        }

        $error = $secondError ?? $firstError;
        if ($error !== null) {
            $reason = match ($error->getErrorType()) {
                NumberParseException::INVALID_COUNTRY_CODE => 'no valid country code found — prefix with the country code (e.g. +966 for Saudi Arabia, +44 for UK)',
                NumberParseException::NOT_A_NUMBER         => 'not a recognisable phone number format',
                NumberParseException::TOO_SHORT_AFTER_IDD  => 'too short after the international dialling prefix',
                NumberParseException::TOO_SHORT_NSN        => 'national number too short for the detected country',
                NumberParseException::TOO_LONG             => 'number too long (' . strlen(preg_replace('/\D/', '', $input)) . ' digits)',
                default                                    => 'could not be parsed',
            };

            return "{$base}: {$reason}.";
        }

        return "{$base}: not a valid number.";
    }
}
