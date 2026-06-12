<?php

namespace App\Support;

class NameNormalizer
{
    /**
     * Particles that stay lowercase when they appear mid-name.
     * The first word of a name is always capitalised regardless.
     * e.g. "Mohamed bin Ahmed", "Jan van der Berg".
     */
    private const LOWERCASE_PARTICLES = [
        'bin', 'binti', 'bte', 'bt',
        'van', 'von', 'de', 'du', 'la', 'le', 'di', 'da',
        'der', 'den', 'ten', 'ter', 'te',
    ];

    /**
     * Prefixes whose capitalisation must be preserved exactly.
     * Key = lowercase trigger, value = correct output.
     */
    private const FIXED_PREFIXES = [
        'mc'  => 'Mc',
        'mac' => 'Mac',
        "o'"  => "O'",
    ];

    /**
     * Normalise a raw name from any source to consistent Title Case.
     *
     * - Collapses whitespace
     * - Converts ALL CAPS or all-lowercase to Title Case
     * - Preserves particles (bin, van, de…) in lowercase
     * - Handles Mc/Mac and O' prefixes correctly
     * - Returns an empty string unchanged
     */
    public static function normalize(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($name === '') {
            return '';
        }

        // Split on spaces and hyphens while keeping the delimiter
        $parts = preg_split('/([\s\-]+)/', $name, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$name];

        $result    = [];
        $wordIndex = 0;
        foreach ($parts as $part) {
            // Preserve delimiters (spaces, hyphens) as-is
            if (trim($part) === '' || $part === '-') {
                $result[] = $part;
                continue;
            }

            // First real word is always capitalised, even if it is a particle
            $result[] = self::capitalizeWord($part, forceCapitalize: $wordIndex === 0);
            $wordIndex++;
        }

        return implode('', $result);
    }

    private static function capitalizeWord(string $word, bool $forceCapitalize = false): string
    {
        $lower = mb_strtolower($word);

        // Particles stay lowercase unless they're the first word
        if (! $forceCapitalize && in_array($lower, self::LOWERCASE_PARTICLES, strict: true)) {
            return $lower;
        }

        // Fixed prefix overrides (Mc, Mac, O')
        foreach (self::FIXED_PREFIXES as $trigger => $output) {
            if (str_starts_with($lower, $trigger) && mb_strlen($lower) > mb_strlen($trigger)) {
                return $output . self::capitalizeWord(mb_substr($word, mb_strlen($trigger)), forceCapitalize: true);
            }
        }

        // Handle apostrophes within words: O'Brien, D'Angelo
        if (str_contains($word, "'")) {
            $sub = explode("'", $word, 2);
            return mb_strtoupper(mb_substr($sub[0], 0, 1)) . mb_strtolower(mb_substr($sub[0], 1))
                . "'"
                . self::capitalizeWord($sub[1], forceCapitalize: true);
        }

        return mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
    }
}
