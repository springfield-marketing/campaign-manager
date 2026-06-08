<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\Ownership;

/**
 * Creates or updates a Client + Ownership row from a fully-resolved import payload.
 *
 * Call this only after all FK IDs have been resolved. If required fields are
 * missing, throw rather than silently inserting incomplete data.
 */
class RawContactImportEnricher
{
    private const RELATIONSHIP_TYPES = [
        'owner', 'resident', 'tenant', 'buyer_interest',
        'seller_interest', 'investor', 'past_owner', 'unknown',
    ];

    private const CONFIDENCE_LEVELS = ['high', 'medium', 'low'];

    /**
     * Upsert a client record from the resolved payload.
     *
     * Pass $existingPhoneNumber if you already looked it up (avoids a second query).
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveClient(array $payload, ?ClientPhoneNumber $existingPhoneNumber = null): Client
    {
        $email = trim((string) ($payload['email'] ?? ''));

        // If the caller already found the phone record, use it directly
        if ($existingPhoneNumber?->client_id) {
            $client = Client::findOrFail($existingPhoneNumber->client_id);
            if ($email !== '') {
                $client->setPrimaryEmailAddress($email);
            }
            return $client;
        }

        // No phone record provided — fall through to name+location lookup
        $normalizedPhone = $payload['normalized_phone'] ?? null;
        if ($normalizedPhone && ! $existingPhoneNumber) {
            $found = ClientPhoneNumber::where('normalized_phone', $normalizedPhone)
                ->whereNotNull('client_id')
                ->first();
            if ($found?->client_id) {
                return Client::findOrFail($found->client_id);
            }
        }

        $fullName   = trim((string) ($payload['name'] ?? ''));
        $emirate    = trim((string) ($payload['emirate'] ?? ''));
        $countryIso = strtoupper(substr(trim((string) ($payload['country_iso'] ?? '')), 0, 2)) ?: null;

        $client = Client::firstOrCreate(
            [
                'full_name'   => $fullName ?: null,
                'emirate'     => $emirate ?: null,
                'country_iso' => $countryIso,
            ],
            [
                'nationality' => $this->blankToNull($payload['nationality'] ?? null),
                'gender'      => $this->blankToNull($payload['gender'] ?? null),
            ]
        );

        if ($email !== '') {
            $client->setPrimaryEmailAddress($email);
        }

        return $client;
    }

    /**
     * Upsert an Ownership row for the given client and resolved location IDs.
     *
     * @param  array<string, mixed>  $payload
     */
    public function syncOwnership(
        Client $client,
        array $payload,
        ?int $officialAreaId,
        ?int $marketingAreaId,
        ?int $projectId,
        ?int $buildingId,
        string $sourceName,
    ): Ownership {
        $emirate          = trim((string) ($payload['emirate'] ?? ''));
        $unitReference    = $this->blankToNull($payload['unit_reference'] ?? null);
        $relationshipType = $this->normalizeRelationshipType($payload['relationship_type'] ?? null);
        $confidenceLevel  = $this->normalizeConfidenceLevel($payload['confidence_level'] ?? null);

        if (! $emirate) {
            throw new \InvalidArgumentException('emirate is required for ownership');
        }
        if (! $marketingAreaId) {
            throw new \InvalidArgumentException('marketing_area_id is required for ownership');
        }

        return Ownership::updateOrCreate(
            [
                'client_id'         => $client->id,
                'emirate'           => $emirate,
                'marketing_area_id' => $marketingAreaId,
                'project_id'        => $projectId,
                'building_id'       => $buildingId,
                'unit_reference'    => $unitReference,
                'relationship_type' => $relationshipType,
            ],
            [
                'official_area_id' => $officialAreaId,
                'confidence_level' => $confidenceLevel,
                'source'           => $sourceName,
            ]
        );
    }

    private function normalizeRelationshipType(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        if ($normalized === '') {
            return 'unknown';
        }

        if (! in_array($normalized, self::RELATIONSHIP_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid relationship type: {$value}");
        }

        return $normalized;
    }

    private function normalizeConfidenceLevel(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (! in_array($normalized, self::CONFIDENCE_LEVELS, true)) {
            throw new \InvalidArgumentException("Invalid confidence level: {$value}");
        }

        return $normalized;
    }

    private function blankToNull(?string $value): ?string
    {
        $v = trim((string) $value);

        return $v === '' ? null : $v;
    }
}
