<?php

namespace App\Modules\WhatsApp\Support;

class WhatsAppRawImportColumnMapper
{
    /**
     * @param  array<int, string>  $headers
     * @return array{mapped: array<string, int>, missing: array<int, string>}
     */
    public function map(array $headers): array
    {
        $aliases  = config('whatsapp.raw_import.aliases', []);
        $required = config('whatsapp.raw_import.required', []);
        $normalized = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $headers);

        $mapped = [];

        foreach ($aliases as $canonical => $candidates) {
            foreach ($candidates as $candidate) {
                $index = array_search(mb_strtolower($candidate), $normalized, true);
                if ($index !== false) {
                    $mapped[$canonical] = $index;
                    break;
                }
            }
        }

        $missing = array_values(array_filter($required, fn ($f) => ! array_key_exists($f, $mapped)));

        return ['mapped' => $mapped, 'missing' => $missing];
    }
}
