<?php

namespace App\Support\Identity;

use App\Models\Client;
use App\Models\ClientAuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Splits a "super client" — one client record that absorbed many unrelated phone numbers via a
 * weak identity key (stub/placeholder name → IMP-001, real-name collision → IMP-002, institution
 * name → IMP-003) — back into one client per phone number.
 *
 * This is the single implementation shared by the `clients:split-name-collisions` command and the
 * Filament "Split into one client per phone" record action, so the console and the dashboard split
 * identically and both leave a reversible `client_audit_logs` snapshot first.
 *
 * What it does NOT do: client-level data (emails, tags, ownerships, alternate_names) stays on the
 * original client — there is no reliable way to attribute it to a specific phone. Only phone-tied
 * data moves: the phone row itself (so its WhatsApp/IVR records, which key off
 * client_phone_number_id, follow automatically) and `client_sources` (which also carries client_id).
 */
class ClientSplitter
{
    /**
     * Split one client into one fresh client per phone number.
     *
     * @return array{split:int, deleted:int} count of phones moved to their own client, and legacy
     *                                       placeholder numbers deleted (they fail the
     *                                       not_placeholder_check CHECK on update — no real contact
     *                                       sits behind a placeholder number).
     */
    public function split(Client $client): array
    {
        $phones = DB::table('client_phone_numbers')
            ->where('client_id', $client->id)
            ->get(['id', 'normalized_phone']);

        $split = 0;
        $deleted = 0;

        DB::transaction(function () use ($client, $phones, &$split, &$deleted): void {
            ClientAuditLog::create([
                'action' => 'split',
                'client_id' => $client->id,
                'reason' => "Super-client split: \"{$client->full_name}\" absorbed {$phones->count()} unrelated phone numbers via a weak identity key (stub/real/institution name)",
                'performed_by' => auth()->user()?->email ?? (get_current_user() ?: 'console'),
                'snapshot' => [
                    'client' => $client->toArray(),
                    'phone_numbers' => $phones->toArray(),
                ],
            ]);

            foreach ($phones as $phone) {
                // A legacy placeholder number (predates not_placeholder_check, never cleaned up)
                // fails the UPDATE — Postgres re-validates the whole row's CHECK constraints on any
                // update, not just the changed column. Use a savepoint so one bad number doesn't
                // abort the rest of the split, then fall back to deleting it.
                try {
                    DB::transaction(function () use ($client, $phone, &$split): void {
                        $newClient = Client::create(['full_name' => $client->full_name]);

                        DB::table('client_phone_numbers')->where('id', $phone->id)->update(['client_id' => $newClient->id]);
                        DB::table('client_sources')->where('client_phone_number_id', $phone->id)->update(['client_id' => $newClient->id]);

                        $split++;
                    });
                } catch (QueryException) {
                    DB::table('client_sources')->where('client_phone_number_id', $phone->id)->delete();
                    DB::table('client_phone_numbers')->where('id', $phone->id)->delete();
                    $deleted++;
                }
            }
        });

        return ['split' => $split, 'deleted' => $deleted];
    }
}
