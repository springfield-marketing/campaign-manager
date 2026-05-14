<?php

namespace App\Support;

use App\Models\Community;
use App\Models\Region;

/**
 * Shared lookup for converting free-text city/community values from CSV
 * imports into region_id / community_id foreign keys.
 *
 * Instantiated once per import job — loads from DB on construction so
 * individual row processing stays in memory with no extra queries.
 */
class GeographyLookup
{
    // District / area names that appear in import files but don't match
    // any region name directly — map them to the parent emirate.
    private const CITY_ALIASES = [
        'Abu Dhabi District'  => 'Abu Dhabi',
        'Abu Dhabi City'      => 'Abu Dhabi',
        'Ras Al Hekma'        => 'Abu Dhabi',
        'Dubailand District'  => 'Dubai',
        'Downtown District'   => 'Dubai',
        'Al Barsha South'     => 'Dubai',
        'Meydan District'     => 'Dubai',
        'Creek District'      => 'Dubai',
        'Dubai Marina'        => 'Dubai',
        'Warsan First'        => 'Dubai',
        'Expo City Dubai'     => 'Dubai',
        'Sheik Zayed'         => 'Dubai',
        'Bur Dubai District'  => 'Dubai',
        'Deira District'      => 'Dubai',
    ];

    // Community text variants that differ from the canonical DB name.
    private const COMMUNITY_ALIASES = [
        'The Palm Jumeirah'              => 'Palm Jumeirah',
        'Downtown'                       => 'Downtown Dubai',
        'Downtown Burj Dubai'            => 'Downtown Dubai',
        'JVC - Jumeirah Village Circle'  => 'Jumeirah Village Circle',
        'MBR - Mohammad Bin Rashid City' => 'Mohammed Bin Rashid City',
    ];

    /** @var array<string, int> region name → id */
    private array $regionIds;

    /** @var array<string, int> community name → id */
    private array $communityIds;

    /** @var array<int, int> community id → region id */
    private array $communityRegionIds;

    public function __construct()
    {
        $this->regionIds = Region::pluck('id', 'name')->all();

        $communities = Community::select('id', 'region_id', 'name')->get();
        $this->communityIds      = $communities->pluck('id', 'name')->all();
        $this->communityRegionIds = $communities->pluck('region_id', 'id')->all();
    }

    public function regionId(string $text): ?int
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return $this->regionIds[$text]
            ?? $this->regionIds[self::CITY_ALIASES[$text] ?? '']
            ?? null;
    }

    public function communityId(string $text): ?int
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return $this->communityIds[$text]
            ?? $this->communityIds[self::COMMUNITY_ALIASES[$text] ?? '']
            ?? null;
    }

    public function regionIdForCommunity(int $communityId): ?int
    {
        return $this->communityRegionIds[$communityId] ?? null;
    }
}
