<?php

namespace App\Support;

use App\Models\Building;
use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\PlaceAlias;
use App\Models\Project;

/**
 * Resolves raw string location names to FK IDs using:
 *   1. Exact match (case-insensitive) on the canonical name
 *   2. Alias lookup via place_aliases table
 *
 * All lookups are cached for the duration of the request / job to avoid
 * hammering the DB on large import batches.
 */
class LocationResolver
{
    /** @var array<string, int|null> */
    private array $cache = [];

    // ── Official areas ──────────────────────────────────────────────────────

    public function officialAreaId(string $emirate, string $rawName): ?int
    {
        $rawName = trim($rawName);
        if ($rawName === '') {
            return null;
        }

        $key = "oa:{$emirate}:{$rawName}";

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // 1. Exact match on canonical name
        $id = OfficialArea::where('emirate', $emirate)
            ->whereRaw('lower(area_name_en) = lower(?)', [$rawName])
            ->where('is_active', true)
            ->value('id');

        // 2. Alias lookup
        if (! $id) {
            $aliasEntityId = PlaceAlias::resolveId('official_area', $rawName);
            if ($aliasEntityId) {
                $id = OfficialArea::where('emirate', $emirate)->where('id', $aliasEntityId)->value('id');
            }
        }

        return $this->cache[$key] = $id;
    }

    // ── Marketing areas ─────────────────────────────────────────────────────

    public function marketingAreaId(string $emirate, string $rawName): ?int
    {
        $rawName = trim($rawName);
        if ($rawName === '') {
            return null;
        }

        $key = "ma:{$emirate}:{$rawName}";

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // 1. Exact match
        $id = MarketingArea::where('emirate', $emirate)
            ->whereRaw('lower(name) = lower(?)', [$rawName])
            ->where('is_active', true)
            ->value('id');

        // 2. Alias lookup
        if (! $id) {
            $aliasEntityId = PlaceAlias::resolveId('marketing_area', $rawName);
            if ($aliasEntityId) {
                $id = MarketingArea::where('emirate', $emirate)->where('id', $aliasEntityId)->value('id');
            }
        }

        return $this->cache[$key] = $id;
    }

    // ── Projects ────────────────────────────────────────────────────────────

    public function projectId(?int $marketingAreaId, string $rawName): ?int
    {
        $rawName = trim($rawName);
        if ($rawName === '') {
            return null;
        }

        $key = "proj:{$marketingAreaId}:{$rawName}";

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $query = Project::whereRaw('lower(name) = lower(?)', [$rawName])->where('is_active', true);

        if ($marketingAreaId) {
            $query->where('marketing_area_id', $marketingAreaId);
        }

        $id = $query->value('id');

        // Alias lookup (project scope, no emirate constraint)
        if (! $id) {
            $aliasEntityId = PlaceAlias::resolveId('project', $rawName);
            if ($aliasEntityId) {
                $checkQuery = Project::where('id', $aliasEntityId)->where('is_active', true);
                if ($marketingAreaId) {
                    $checkQuery->where('marketing_area_id', $marketingAreaId);
                }
                $id = $checkQuery->value('id');
            }
        }

        return $this->cache[$key] = $id;
    }

    // ── Buildings ────────────────────────────────────────────────────────────

    public function buildingId(?int $projectId, string $rawName): ?int
    {
        $rawName = trim($rawName);
        if ($rawName === '') {
            return null;
        }

        $key = "bld:{$projectId}:{$rawName}";

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $query = Building::whereRaw('lower(name) = lower(?)', [$rawName])->where('is_active', true);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $id = $query->value('id');

        if (! $id) {
            $aliasEntityId = PlaceAlias::resolveId('building', $rawName);
            if ($aliasEntityId) {
                $checkQuery = Building::where('id', $aliasEntityId)->where('is_active', true);
                if ($projectId) {
                    $checkQuery->where('project_id', $projectId);
                }
                $id = $checkQuery->value('id');
            }
        }

        return $this->cache[$key] = $id;
    }
}
