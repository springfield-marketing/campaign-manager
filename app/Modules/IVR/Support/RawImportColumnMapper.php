<?php

namespace App\Modules\IVR\Support;

class RawImportColumnMapper
{
    /**
     * @param  array<int, string>  $headers
     * @return array{mapped: array<string, int>, missing: array<int, string>}
     */
    public function map(array $headers): array
    {
        $aliases = config('ivr.raw_import.aliases', []);
        $required = config('ivr.raw_import.required', []);
        $normalizedHeaders = array_map(
            fn ($header) => mb_strtolower(trim((string) $header)),
            $headers,
        );

        $mapped = [];

        foreach ($aliases as $canonical => $candidates) {
            foreach ($candidates as $candidate) {
                $index = array_search(mb_strtolower($candidate), $normalizedHeaders, true);

                if ($index !== false) {
                    $mapped[$canonical] = $index;
                    break;
                }
            }
        }

        $missing = array_values(array_filter(
            $required,
            fn ($field) => ! array_key_exists($field, $mapped),
        ));

        return [
            'mapped' => $mapped,
            'missing' => $missing,
        ];
    }
}
