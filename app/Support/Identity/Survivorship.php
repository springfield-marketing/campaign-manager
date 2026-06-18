<?php

namespace App\Support\Identity;

/**
 * Picks the surviving value for a field when multiple sources disagree, per
 * docs/data-rules/contact-data-spec.md §3.
 *
 * Rules:
 *   1. Blank candidates are ignored.
 *   2. Highest trust wins (see {@see SourceTrust}).
 *   3. Ties break to the most recent, then to the most complete (longest) value.
 *   4. The losing values are NEVER discarded — they are returned as `alternates` so the
 *      caller can retain them in provenance (client_sources.metadata / clients.alternate_names).
 *      The "winner" is therefore only a view and is always re-derivable.
 *
 * Each candidate is an array: ['value' => mixed, 'trust' => int, 'at' => int|null]
 * where `at` is a sortable recency key (e.g. a unix timestamp); higher = more recent.
 */
class Survivorship
{
    /**
     * @param  array<int, array{value: mixed, trust?: int, at?: int|null}>  $candidates
     * @return array{value: mixed, alternates: array<int, mixed>}
     */
    public static function resolve(array $candidates): array
    {
        return self::pick($candidates, stubsCanWin: true);
    }

    /**
     * Name-aware resolution: a real name NEVER loses to a stub/placeholder, regardless of the
     * stub's source trust (INV-1 corollary). Only if every candidate is a stub does a stub win.
     *
     * @param  array<int, array{value: mixed, trust?: int, at?: int|null}>  $candidates
     * @return array{value: mixed, alternates: array<int, mixed>}
     */
    public static function resolveName(array $candidates): array
    {
        return self::pick($candidates, stubsCanWin: false);
    }

    /**
     * @param  array<int, array{value: mixed, trust?: int, at?: int|null}>  $candidates
     * @return array{value: mixed, alternates: array<int, mixed>}
     */
    private static function pick(array $candidates, bool $stubsCanWin): array
    {
        // 1. Drop blanks.
        $candidates = array_values(array_filter(
            $candidates,
            fn (array $c) => ! self::isBlank($c['value'] ?? null),
        ));

        if ($candidates === []) {
            return ['value' => null, 'alternates' => []];
        }

        // Eligibility for *winning*: when stubs can't win, prefer real names — but only if at
        // least one real name exists; otherwise fall back to the stubs so we still return a value.
        $eligible = $candidates;
        if (! $stubsCanWin) {
            $real = array_values(array_filter(
                $candidates,
                fn (array $c) => ! NameClassifier::isStub((string) $c['value']),
            ));
            if ($real !== []) {
                $eligible = $real;
            }
        }

        // 2–3. Highest trust, then most recent, then most complete.
        usort($eligible, function (array $a, array $b): int {
            return [($b['trust'] ?? SourceTrust::DEFAULT_RANK), ($b['at'] ?? 0), self::length($b['value'])]
               <=> [($a['trust'] ?? SourceTrust::DEFAULT_RANK), ($a['at'] ?? 0), self::length($a['value'])];
        });

        $winner = $eligible[0]['value'];

        // 4. Every other distinct value becomes an alternate.
        $alternates = [];
        foreach ($candidates as $c) {
            if (! self::sameValue($c['value'], $winner) && ! self::containsValue($alternates, $c['value'])) {
                $alternates[] = $c['value'];
            }
        }

        return ['value' => $winner, 'alternates' => $alternates];
    }

    private static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    private static function length(mixed $value): int
    {
        return is_string($value) ? mb_strlen(trim($value)) : 0;
    }

    private static function sameValue(mixed $a, mixed $b): bool
    {
        if (is_string($a) && is_string($b)) {
            return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
        }

        return $a === $b;
    }

    /**
     * @param  array<int, mixed>  $haystack
     */
    private static function containsValue(array $haystack, mixed $value): bool
    {
        foreach ($haystack as $item) {
            if (self::sameValue($item, $value)) {
                return true;
            }
        }

        return false;
    }
}
