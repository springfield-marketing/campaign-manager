<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\Ownership;
use App\Models\Tag;
use App\Support\Identity\NameClassifier;

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
        'seller_interest', 'investor', 'past_owner', 'prospect', 'unknown',
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

        $fullName = trim((string) ($payload['name'] ?? ''));
        $emirate = trim((string) ($payload['emirate'] ?? ''));
        $countryIso = strtoupper(substr(trim((string) ($payload['country_iso'] ?? '')), 0, 2)) ?: null;

        // Create a fresh client and NEVER match/merge by name. Phone is the identity anchor
        // (matched above); a name — of ANY kind — is not a safe identity key:
        //   - IMP-001: a stub/placeholder name ("Instagram Dm", "No Name", a bare first name) is
        //     too weak — matching the (name, emirate, country) tuple is how unrelated people
        //     collapsed onto one "super client" with many numbers attached.
        //   - IMP-002: a real personal name on a brand-new phone is still not a safe merge key —
        //     the same name is not the same person (two people share a name). We bias to
        //     under-merge; genuine same-person duplicates are surfaced by
        //     clients:audit-data-quality and the review queue, not guessed here.
        //   - IMP-003: an institution name (bank / developer / agency) is the WORST anchor of
        //     all. In DLD/owner data a bank is the registered owner/mortgagee of hundreds of
        //     unrelated properties, so firstOrCreate on the institution tuple filed every
        //     individual's mobile under "Emirates Islamic Bank" et al. — a super-client of
        //     strangers. Institutions now create-fresh per phone like everything else; a bank
        //     never "owns" a contact's number. (Reverses the one carve-out IMP-002 kept.)
        // Re-imports of the same number match by phone above, so this does not duplicate them.
        // See docs/data-rules/imports.md and contact-data-spec.md §4–§5.
        $client = Client::create([
            'full_name' => $fullName ?: null,
            'emirate' => $emirate ?: null,
            'country_iso' => $countryIso,
            'nationality' => $this->blankToNull($payload['nationality'] ?? null),
            'gender' => $this->blankToNull($payload['gender'] ?? null),
            // IMP-003: flag organisation names (developer/bank/LLC) so they can be excluded from
            // the contacts list. They create-fresh like everything else; this only labels them.
            'is_institution' => $fullName !== '' && NameClassifier::isInstitution($fullName),
        ]);

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
        $emirate = trim((string) ($payload['emirate'] ?? ''));
        $unitReference = $this->blankToNull($payload['unit_reference'] ?? null);
        $relationshipType = $this->normalizeRelationshipType($payload['relationship_type'] ?? null);
        $confidenceLevel = $this->normalizeConfidenceLevel($payload['confidence_level'] ?? null);

        if (! $emirate) {
            throw new \InvalidArgumentException('emirate is required for ownership');
        }
        if (! $marketingAreaId) {
            throw new \InvalidArgumentException('marketing_area_id is required for ownership');
        }

        $matchFields = [
            'client_id' => $client->id,
            'emirate' => $emirate,
            'marketing_area_id' => $marketingAreaId,
            'project_id' => $projectId,
            'building_id' => $buildingId,
            'unit_reference' => $unitReference,
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
                'source_names' => $sourceNames,
            ])->save();

            return $existing;
        }

        return Ownership::create(array_merge($matchFields, [
            'official_area_id' => $officialAreaId,
            'confidence_level' => $confidenceLevel,
            'last_source_name' => $sourceName,
            'source_names' => [$sourceName],
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
        if ($val = $this->blankToNull($payload['notes'] ?? null)) {
            if (blank($client->notes)) {
                $updates['notes'] = $val;
            } elseif (! str_contains((string) $client->notes, $val)) {
                $updates['notes'] = trim((string) $client->notes)."\n\n".now()->format('d M Y').': '.$val;
            }
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
     * Returns true when the stored name looks like a placeholder or garbage value that should
     * always be overwritten by a real imported name, and — critically — should never be used
     * to match/merge this row's identity with another row's. A handful of "super clients" with
     * hundreds of unrelated phone numbers attached were caused by exactly this: many different
     * real people whose row literally said "No Name", "Guest", or "Ahmed Na" all collapsed onto
     * one client record because the name was treated as a reliable identity key (IMP-001).
     *
     * The detection itself lives in the canonical {@see NameClassifier}
     * so the import path, the data-quality audit, and the review queue all agree. This method
     * is kept as a thin alias for existing callers.
     */
    public static function isStubName(string $name): bool
    {
        return NameClassifier::isStub($name);
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
