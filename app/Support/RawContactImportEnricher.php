<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\Ownership;
use App\Models\Tag;
use App\Support\NameNormalizer;

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
            $this->enrichClientBlanks($client, $payload);
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
                $client = Client::findOrFail($found->client_id);
                $this->enrichClientBlanks($client, $payload);
                if ($email !== '') {
                    $client->setPrimaryEmailAddress($email);
                }
                return $client;
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

        $matchFields = [
            'client_id'         => $client->id,
            'emirate'           => $emirate,
            'marketing_area_id' => $marketingAreaId,
            'project_id'        => $projectId,
            'building_id'       => $buildingId,
            'unit_reference'    => $unitReference,
            'relationship_type' => $relationshipType,
        ];

        $existing = Ownership::where($matchFields)->first();

        if ($existing) {
            $sourceNames = $existing->source_names ?? [];
            if (! in_array($sourceName, $sourceNames, true)) {
                $sourceNames[] = $sourceName;
            }

            $existing->fill([
                'official_area_id' => $officialAreaId,
                'confidence_level' => Ownership::higherConfidence($existing->confidence_level, $confidenceLevel),
                'last_source_name' => $sourceName,
                'source_names'     => $sourceNames,
            ])->save();

            return $existing;
        }

        return Ownership::create(array_merge($matchFields, [
            'official_area_id'  => $officialAreaId,
            'confidence_level'  => $confidenceLevel,
            'last_source_name'  => $sourceName,
            'source_names'      => [$sourceName],
            'first_confirmed_at' => now(),
        ]));
    }

    private function enrichClientBlanks(Client $client, array $payload): void
    {
        $updates = [];

        $importedName = NameNormalizer::normalize($this->blankToNull($payload['name'] ?? null) ?? '');
        if ($importedName !== '') {
            if (blank($client->full_name)) {
                // Blank stored name — always fill it in
                $updates['full_name'] = $importedName;
            } elseif (mb_strtolower($client->full_name) === mb_strtolower($importedName)) {
                // Same name, different case — upgrade to the normalised version
                $updates['full_name'] = $importedName;
            } elseif (self::isStubName($client->full_name)) {
                // Stored name is a placeholder — replace with the real one
                $updates['full_name'] = $importedName;
            }
            // Genuinely different names: leave full_name untouched.
            // The imported name is preserved in client_sources.metadata.raw_name.
        }
        if (blank($client->emirate) && $emirate = $this->blankToNull($payload['emirate'] ?? null)) {
            $updates['emirate'] = $emirate;
        }
        if (blank($client->nationality) && $val = $this->blankToNull($payload['nationality'] ?? null)) {
            $updates['nationality'] = $val;
        }
        if (blank($client->gender) && $val = $this->blankToNull($payload['gender'] ?? null)) {
            $updates['gender'] = $val;
        }
        if (blank($client->interest) && $val = $this->blankToNull($payload['interest'] ?? null)) {
            $updates['interest'] = $val;
        }
        if (blank($client->notes) && $val = $this->blankToNull($payload['notes'] ?? null)) {
            $updates['notes'] = $val;
        }
        if (blank($client->country_iso)) {
            $iso = strtoupper(substr(trim((string) ($payload['country_iso'] ?? '')), 0, 2)) ?: null;
            if ($iso) {
                $updates['country_iso'] = $iso;
            }
        }

        if ($updates !== []) {
            $client->fill($updates)->save();
        }

        // Tags are additive — always add specified tags, never remove existing ones.
        if ($rawTags = $this->blankToNull($payload['tags'] ?? null)) {
            $tagNames = array_values(array_filter(array_map('trim', explode(',', $rawTags))));

            if ($tagNames !== []) {
                $tagIds = array_map(
                    fn (string $name) => Tag::firstOrCreate(['name' => $name])->id,
                    $tagNames,
                );
                $client->tags()->syncWithoutDetaching($tagIds);
            }
        }
    }

    /**
     * Returns true when the stored name looks like a placeholder or garbage value
     * that should always be overwritten by a real imported name.
     */
    public static function isStubName(string $name): bool
    {
        $trimmed = trim($name);

        // Explicit DND / Do Not Call placeholders
        if (in_array(strtoupper($trimmed), ['DND', 'DO NOT CALL', 'DO NOT DISTURB', 'N/A', '-', '.'], strict: true)) {
            return true;
        }

        // Very short (1–2 real characters, possibly with punctuation)
        if (mb_strlen(preg_replace('/[\s.\-_]/u', '', $trimmed) ?? '') <= 2) {
            return true;
        }

        // Ends with a bare dot/space (e.g. "Ahmed .") — truncated or partial
        if (preg_match('/\.\s*$/', $trimmed)) {
            return true;
        }

        return false;
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
