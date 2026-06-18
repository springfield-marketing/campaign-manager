<?php

namespace App\Support\Identity;

/**
 * Ranks the trustworthiness of a data source, per docs/data-rules/contact-data-spec.md §3.
 *
 * Survivorship (which value wins when sources disagree) is driven primarily by this rank: a
 * higher-trust source may overwrite a lower-trust value, never the reverse. The ranking is
 * keyed on `client_sources.source_type`.
 *
 * The exact numbers are not load-bearing — only their order is. They are spaced out so new
 * source types can be slotted in without renumbering.
 */
class SourceTrust
{
    /** Higher = more trustworthy. */
    private const RANKS = [
        'manual'            => 100, // a human typed it directly
        'admin'             => 100,
        'staging_promoted'  => 90,  // reviewed and approved in the import review queue
        'crm_verified'      => 85,  // corroborated by an external CRM / verified lead
        'campaign_result'   => 70,  // the person actually responded to a campaign
        'raw_import'        => 50,  // a bulk import row
    ];

    /** Trust of an otherwise-unknown source type. */
    public const DEFAULT_RANK = 40;

    public static function rank(?string $sourceType): int
    {
        $key = strtolower(trim((string) $sourceType));

        return self::RANKS[$key] ?? self::DEFAULT_RANK;
    }

    /**
     * True when source $a is strictly more trustworthy than source $b.
     */
    public static function outranks(?string $a, ?string $b): bool
    {
        return self::rank($a) > self::rank($b);
    }
}
