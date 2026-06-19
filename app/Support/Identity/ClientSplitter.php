<?php

namespace App\Support\Identity;

use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Support\NameNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Splits a "super client" — one client record that absorbed many unrelated phone numbers via a
 * weak identity key (stub name → IMP-001, real-name collision → IMP-002, institution name →
 * IMP-003) — back into one client per phone number, recovering each number's true owner.
 *
 * Provenance-based, not destructive. The correct name→number mapping was never lost: every
 * number's original imported name is preserved in `client_sources.metadata.raw_name`. So instead
 * of blindly fragmenting into placeholder husks, we:
 *
 *   1. Keep an ANCHOR number on the original client — the number whose source produced the
 *      client's current name (e.g. the bank's office line that the DLD "Owners" import named
 *      "Emirates Islamic Bank"). The original keeps its name, its anchor number, AND its
 *      client-level data (emails, tags, ownerships).
 *   2. Move every OTHER number to its own fresh client, named from THAT number's own earliest
 *      real `raw_name` (recovering "Saeed Abdulla", "Fatima Rashed", …). A number with no
 *      recoverable real name (IVR-only, blank, "N/A") gets its own client with a BLANK name —
 *      phone is the identity; we never copy the misleading inherited name onto it.
 *
 * Campaign history is safe: whatsapp_messages, ivr_call_records, contact_suppressions and
 * ivr_phone_profiles all key off `client_phone_number_id`, never client_id — so moving a number
 * (reassigning its client_id, not recreating the row) carries all its history with it untouched.
 *
 * Reversible: a full snapshot is written to `client_audit_logs` before any change.
 */
class ClientSplitter
{
    /**
     * Build the split plan WITHOUT mutating anything — powers the dry-run preview.
     *
     * @return array{
     *   full_name: ?string,
     *   anchor_cpn: ?int,
     *   rows: array<int, array{cpn:int, phone:string, role:string, name:?string, messages:int, calls:int}>
     * }
     */
    public function preview(Client $client): array
    {
        $phones = DB::table('client_phone_numbers')
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->get(['id', 'raw_phone', 'normalized_phone']);

        $currentName = NameNormalizer::normalize((string) $client->full_name);
        $anchorCpn = $this->chooseAnchor($phones, $currentName);

        $rows = [];
        foreach ($phones as $phone) {
            $isAnchor = $phone->id === $anchorCpn;
            $derived = $isAnchor ? $client->full_name : $this->recoverName($phone->id);

            $rows[] = [
                'cpn' => $phone->id,
                'phone' => $phone->raw_phone ?: ($phone->normalized_phone ?: '—'),
                'role' => $isAnchor ? 'anchor' : 'move',
                'name' => $derived,
                'messages' => DB::table('whatsapp_messages')->where('client_phone_number_id', $phone->id)->count(),
                'calls' => DB::table('ivr_call_records')->where('client_phone_number_id', $phone->id)->count(),
            ];
        }

        return [
            'full_name' => $client->full_name,
            'anchor_cpn' => $anchorCpn,
            'rows' => $rows,
        ];
    }

    /**
     * Execute the split. The anchor number stays on $client; every other number moves to its own
     * client (named from its own provenance, or blank). Returns counts for reporting.
     *
     * @return array{moved:int, deleted:int, kept:int}
     */
    public function split(Client $client): array
    {
        $phones = DB::table('client_phone_numbers')
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->get(['id', 'raw_phone', 'normalized_phone']);

        if ($phones->count() <= 1) {
            return ['moved' => 0, 'deleted' => 0, 'kept' => $phones->count()];
        }

        $currentName = NameNormalizer::normalize((string) $client->full_name);
        $anchorCpn = $this->chooseAnchor($phones, $currentName);

        $moved = 0;
        $deleted = 0;
        $movedNames = [];

        DB::transaction(function () use ($client, $phones, $anchorCpn, &$moved, &$deleted, &$movedNames): void {
            ClientAuditLog::create([
                'action' => 'split',
                'client_id' => $client->id,
                'reason' => "Super-client split: \"{$client->full_name}\" held {$phones->count()} unrelated phone numbers; kept the anchor number and moved the rest to their own (provenance-named) clients",
                'performed_by' => auth()->user()?->email ?? (get_current_user() ?: 'console'),
                'snapshot' => [
                    'client' => $client->toArray(),
                    'phone_numbers' => $phones->toArray(),
                    'anchor_cpn' => $anchorCpn,
                ],
            ]);

            foreach ($phones as $phone) {
                if ($phone->id === $anchorCpn) {
                    continue; // anchor stays on the original client
                }

                $name = $this->recoverName($phone->id);

                // A legacy placeholder number (predates not_placeholder_check, never cleaned up)
                // fails the UPDATE — Postgres re-validates the whole row's CHECK constraints on any
                // update. Savepoint so one bad number doesn't abort the split; fall back to delete.
                try {
                    DB::transaction(function () use ($phone, $name, &$moved, &$movedNames): void {
                        $newClient = Client::create(['full_name' => $name]);

                        DB::table('client_phone_numbers')->where('id', $phone->id)->update(['client_id' => $newClient->id]);
                        DB::table('client_sources')->where('client_phone_number_id', $phone->id)->update(['client_id' => $newClient->id]);

                        if ($name !== null) {
                            $movedNames[] = $name;
                        }
                        $moved++;
                    });
                } catch (QueryException) {
                    DB::table('client_sources')->where('client_phone_number_id', $phone->id)->delete();
                    DB::table('client_phone_numbers')->where('id', $phone->id)->delete();
                    $deleted++;
                }
            }

            // The moved people no longer belong to this record — prune their names from the
            // original's alternate_names so it stops looking like a super-client.
            $this->pruneAlternateNames($client, $movedNames);
        });

        return ['moved' => $moved, 'deleted' => $deleted, 'kept' => 1];
    }

    /**
     * The anchor is the number whose own provenance produced the client's current display name —
     * that's the number the name legitimately belongs to. If none matches (the name came from a
     * property/ownership import with no phone, or is blank), fall back to the oldest number on the
     * record (lowest id = first imported), which the original keeps.
     *
     * @param  Collection<int, object>  $phones
     */
    private function chooseAnchor($phones, string $normalizedCurrentName): ?int
    {
        if ($phones->isEmpty()) {
            return null;
        }

        if ($normalizedCurrentName !== '') {
            foreach ($phones as $phone) {
                $name = $this->recoverName($phone->id);
                if ($name !== null && mb_strtolower(NameNormalizer::normalize($name)) === mb_strtolower($normalizedCurrentName)) {
                    return $phone->id;
                }
            }
        }

        return $phones->first()->id;
    }

    /**
     * Recover a number's true owner name from its earliest source carrying a usable real name.
     * Returns null when none is recoverable (IVR-only number, blank, or a stub like "N/A").
     */
    private function recoverName(int $cpnId): ?string
    {
        $sources = DB::table('client_sources')
            ->where('client_phone_number_id', $cpnId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('metadata');

        foreach ($sources as $metadata) {
            $meta = is_string($metadata) ? json_decode($metadata, true) : ($metadata ?? []);
            $raw = trim((string) ($meta['raw_name'] ?? $meta['name'] ?? ''));

            if ($raw !== '' && ! NameClassifier::isStub($raw)) {
                return NameNormalizer::normalize($raw);
            }
        }

        return null;
    }

    /** @param  array<int, string>  $movedNames */
    private function pruneAlternateNames(Client $client, array $movedNames): void
    {
        $existing = $client->alternate_names;
        if (! is_array($existing) || $existing === [] || $movedNames === []) {
            return;
        }

        $movedLower = array_map(fn (string $n) => mb_strtolower($n), $movedNames);
        $kept = array_values(array_filter(
            $existing,
            fn ($n) => ! in_array(mb_strtolower((string) $n), $movedLower, true),
        ));

        if (count($kept) !== count($existing)) {
            $client->forceFill(['alternate_names' => $kept ?: null])->save();
        }
    }
}
