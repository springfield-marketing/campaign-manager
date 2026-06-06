<?php

namespace App\Support;

use App\Models\ImportReviewQueue;
use App\Models\ImportStaging;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Processes one CSV row through the staging pipeline:
 *
 *   1. Insert raw row into import_staging (status=pending)
 *   2. Attempt to resolve all location FKs via LocationResolver
 *   3. If fully resolved → status=matched (ready for promotion)
 *   4. If partially resolved or ambiguous → status=needs_review + add to import_review_queue
 *   5. If missing required fields → status=rejected
 */
class ImportStagingProcessor
{
    private const REQUIRED_FOR_OWNERSHIP = ['emirate', 'relationship_type', 'source'];
    private const REQUIRED_ALWAYS        = ['phone'];

    public function __construct(
        private readonly LocationResolver $resolver,
    ) {
    }

    /**
     * Process a single CSV row. Returns the staging record.
     *
     * @param  array<string, string|null>  $row  Parsed CSV row
     * @param  string  $batchId  UUID for the current import batch
     */
    public function processRow(array $row, string $batchId): ImportStaging
    {
        $staging = $this->createStagingRow($row, $batchId);

        // Hard rejection: no phone at all
        if (empty(trim((string) $staging->phone))) {
            $staging->update([
                'status'        => ImportStaging::STATUS_REJECTED,
                'status_reason' => 'missing phone',
            ]);

            return $staging;
        }

        // Hard rejection: no relationship_type or source
        foreach (self::REQUIRED_FOR_OWNERSHIP as $field) {
            if (empty(trim((string) ($row[$field] ?? '')))) {
                $staging->update([
                    'status'        => ImportStaging::STATUS_REJECTED,
                    'status_reason' => "missing required field: {$field}",
                ]);

                return $staging;
            }
        }

        $emirate = trim((string) ($row['emirate'] ?? ''));

        // Resolve location FKs
        $officialAreaId  = null;
        $marketingAreaId = null;
        $projectId       = null;
        $buildingId      = null;

        $rawOfficialArea  = trim((string) ($row['official_area_name'] ?? ''));
        $rawMarketingArea = trim((string) ($row['marketing_area_name'] ?? ''));
        $rawProject       = trim((string) ($row['project_name'] ?? ''));
        $rawBuilding      = trim((string) ($row['building_name'] ?? ''));

        if ($rawOfficialArea !== '') {
            $officialAreaId = $this->resolver->officialAreaId($emirate, $rawOfficialArea);
        }

        if ($rawMarketingArea !== '') {
            $marketingAreaId = $this->resolver->marketingAreaId($emirate, $rawMarketingArea);
        }

        if ($rawProject !== '') {
            $projectId = $this->resolver->projectId($marketingAreaId, $rawProject);
        }

        if ($rawBuilding !== '') {
            $buildingId = $this->resolver->buildingId($projectId, $rawBuilding);
        }

        // Update staging with resolved IDs
        $staging->update([
            'official_area_id'  => $officialAreaId,
            'marketing_area_id' => $marketingAreaId,
            'project_id'        => $projectId,
            'building_id'       => $buildingId,
        ]);

        // Determine if we can auto-match
        $issues = $this->detectIssues(
            emirate: $emirate,
            marketingAreaId: $marketingAreaId,
            rawOfficialArea: $rawOfficialArea,
            officialAreaId: $officialAreaId,
            rawProject: $rawProject,
            projectId: $projectId,
            rawBuilding: $rawBuilding,
            buildingId: $buildingId,
        );

        if (empty($issues)) {
            $staging->update(['status' => ImportStaging::STATUS_MATCHED]);
        } else {
            $staging->update([
                'status'        => ImportStaging::STATUS_NEEDS_REVIEW,
                'status_reason' => implode('; ', $issues),
            ]);

            ImportReviewQueue::create([
                'batch_id'                   => $batchId,
                'staging_id'                 => $staging->id,
                'name'                       => $staging->name,
                'phone'                      => $staging->phone,
                'email'                      => $staging->email,
                'emirate'                    => $staging->emirate,
                'raw_official_area'          => $staging->raw_official_area,
                'raw_marketing_area'         => $staging->raw_marketing_area,
                'raw_project_name'           => $staging->raw_project_name,
                'raw_building_name'          => $staging->raw_building_name,
                'raw_unit_reference'         => $staging->raw_unit_reference,
                'relationship_type'          => $staging->relationship_type,
                'confidence_level'           => $staging->confidence_level,
                'source'                     => $staging->source,
                'suggested_official_area_id' => $officialAreaId,
                'suggested_marketing_area_id' => $marketingAreaId,
                'suggested_project_id'       => $projectId,
                'suggested_building_id'      => $buildingId,
                'issue_reason'               => implode('; ', $issues),
            ]);
        }

        return $staging;
    }

    /**
     * Promote all matched staging rows for a batch into contacts + ownerships.
     * Returns count of promoted rows.
     */
    public function promoteMatched(string $batchId, \App\Modules\IVR\Support\PhoneNormalizer $phoneNormalizer): int
    {
        $enricher = app(RawContactImportEnricher::class);
        $promoted = 0;

        ImportStaging::where('batch_id', $batchId)
            ->where('status', ImportStaging::STATUS_MATCHED)
            ->lazyById()
            ->each(function (ImportStaging $row) use ($enricher, $phoneNormalizer, &$promoted): void {
                DB::transaction(function () use ($row, $enricher, $phoneNormalizer, &$promoted): void {
                    $normalizedPhone = null;

                    if ($row->phone) {
                        try {
                            $normalizedPhone = $phoneNormalizer->normalize($row->phone)['normalized_phone'] ?? null;
                        } catch (\Throwable) {
                            // Phone normalisation failure is non-fatal; contact still gets created
                        }
                    }

                    $payload = [
                        'name'              => $row->name,
                        'email'             => $row->email,
                        'country_iso'       => $row->country_iso,
                        'emirate'           => $row->emirate,
                        'relationship_type' => $row->relationship_type,
                        'confidence_level'  => $row->confidence_level,
                        'unit_reference'    => $row->raw_unit_reference,
                        'normalized_phone'  => $normalizedPhone,
                    ];

                    $client = $enricher->resolveClient($payload);

                    // Link phone number to client
                    if ($normalizedPhone) {
                        \App\Models\ClientPhoneNumber::where('normalized_phone', $normalizedPhone)
                            ->whereNull('client_id')
                            ->update(['client_id' => $client->id]);
                    }

                    $enricher->syncOwnership(
                        client: $client,
                        payload: $payload,
                        officialAreaId: $row->official_area_id,
                        marketingAreaId: $row->marketing_area_id,
                        projectId: $row->project_id,
                        buildingId: $row->building_id,
                        sourceName: $row->source ?? 'import',
                    );

                    $promoted++;
                });
            });

        return $promoted;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /** @return array<string, string|null> */
    private function createStagingRow(array $row, string $batchId): ImportStaging
    {
        return ImportStaging::create([
            'batch_id'            => $batchId,
            'name'                => $this->blankToNull($row['name'] ?? null),
            'phone'               => $this->blankToNull($row['phone'] ?? null),
            'email'               => $this->blankToNull($row['email'] ?? null),
            'country_iso'         => strtoupper(substr(trim((string) ($row['country_iso'] ?? '')), 0, 2)) ?: null,
            'emirate'             => $this->blankToNull($row['emirate'] ?? null),
            'raw_official_area'   => $this->blankToNull($row['official_area_name'] ?? null),
            'raw_marketing_area'  => $this->blankToNull($row['marketing_area_name'] ?? null),
            'raw_project_name'    => $this->blankToNull($row['project_name'] ?? null),
            'raw_building_name'   => $this->blankToNull($row['building_name'] ?? null),
            'raw_unit_reference'  => $this->blankToNull($row['unit_reference'] ?? null),
            'relationship_type'   => $this->blankToNull($row['relationship_type'] ?? null),
            'confidence_level'    => $this->blankToNull($row['confidence_level'] ?? null),
            'source'              => $this->blankToNull($row['source'] ?? null),
            'status'              => ImportStaging::STATUS_PENDING,
        ]);
    }

    /** @return string[] */
    private function detectIssues(
        string $emirate,
        ?int $marketingAreaId,
        string $rawOfficialArea,
        ?int $officialAreaId,
        string $rawProject,
        ?int $projectId,
        string $rawBuilding,
        ?int $buildingId,
    ): array {
        $issues = [];

        if (! $emirate) {
            $issues[] = 'missing emirate';
        }

        if (! $marketingAreaId) {
            $issues[] = 'unknown marketing area';
        }

        // Dubai requires official_area_id when a raw name was provided
        if ($emirate === 'Dubai' && $rawOfficialArea !== '' && ! $officialAreaId) {
            $issues[] = 'unknown official area for Dubai';
        }

        if ($rawProject !== '' && ! $projectId) {
            $issues[] = 'project not found';
        }

        if ($rawBuilding !== '' && ! $buildingId) {
            $issues[] = 'building not found';
        }

        return $issues;
    }

    private function blankToNull(?string $value): ?string
    {
        $v = trim((string) $value);

        return $v === '' ? null : $v;
    }
}
