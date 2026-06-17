<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientAuditLog;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SplitNameCollisionClients extends Command
{
    protected $signature = 'clients:split-name-collisions
                            {--threshold=10 : Minimum phone-number count to treat a client as a name collision}
                            {--apply : Actually split (default is dry run)}';

    protected $description = 'Split a client that absorbed many unrelated phone numbers via a stub/placeholder name (e.g. "No Name", "Guest") back into one client per phone number';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        $candidates = DB::select('
            SELECT c.id, c.full_name, count(cpn.id) AS phone_count
            FROM clients c
            JOIN client_phone_numbers cpn ON cpn.client_id = c.id
            GROUP BY c.id, c.full_name
            HAVING count(cpn.id) >= ?
            ORDER BY phone_count DESC
        ', [$threshold]);

        if ($candidates === []) {
            $this->info("No clients found with {$threshold}+ phone numbers.");
            return self::SUCCESS;
        }

        $this->table(
            ['Client ID', 'Name', 'Phone Count'],
            array_map(fn ($c) => [$c->id, $c->full_name, $c->phone_count], $candidates),
        );

        if (! $this->option('apply')) {
            $this->info('Dry run only. Re-run with --apply to actually split.');
            $this->warn(
                'Note: client-level data (emails, tags, ownerships, alternate_names) is NOT split — '.
                "it stays on the original client since there's no reliable way to attribute it to a specific phone. ".
                'Only phone-tied data (sources, call records, messages, suppressions) is split out.'
            );
            return self::SUCCESS;
        }

        $totalSplit = 0;
        $totalDeletedAsPlaceholder = 0;

        foreach ($candidates as $candidate) {
            $clientId = $candidate->id;
            $client = Client::find($clientId);
            if (! $client) {
                continue;
            }

            $phones = DB::table('client_phone_numbers')->where('client_id', $clientId)->get(['id', 'normalized_phone']);

            DB::transaction(function () use ($client, $clientId, $phones, &$totalSplit, &$totalDeletedAsPlaceholder) {
                ClientAuditLog::create([
                    'action' => 'split',
                    'client_id' => $clientId,
                    'reason' => "Name collision: \"{$client->full_name}\" absorbed {$phones->count()} unrelated phone numbers via stub-name matching",
                    'performed_by' => get_current_user() ?: 'console',
                    'snapshot' => [
                        'client' => $client->toArray(),
                        'phone_numbers' => $phones->toArray(),
                    ],
                ]);

                foreach ($phones as $phone) {
                    // Try to give this phone its own client. A legacy placeholder number
                    // (predates the not_placeholder_check constraint, never cleaned up) will
                    // fail this UPDATE — Postgres re-validates the whole row's CHECK constraints
                    // on any update, not just the changed column. Use a savepoint so that
                    // failure doesn't abort the rest of this client's split, then fall back to
                    // deleting the phone — there's no real contact behind a placeholder number.
                    try {
                        DB::transaction(function () use ($client, $phone, &$totalSplit) {
                            $newClient = Client::create(['full_name' => $client->full_name]);

                            DB::table('client_phone_numbers')->where('id', $phone->id)->update(['client_id' => $newClient->id]);
                            DB::table('client_sources')->where('client_phone_number_id', $phone->id)->update(['client_id' => $newClient->id]);

                            $totalSplit++;
                        });
                    } catch (QueryException $e) {
                        DB::table('client_sources')->where('client_phone_number_id', $phone->id)->delete();
                        DB::table('client_phone_numbers')->where('id', $phone->id)->delete();
                        $totalDeletedAsPlaceholder++;
                    }
                }
            });

            $this->line("Split client #{$clientId} (\"{$client->full_name}\") into {$phones->count()} separate clients.");
        }

        $this->info("Done. {$totalSplit} phone number(s) moved to their own client, {$totalDeletedAsPlaceholder} legacy placeholder number(s) deleted.");

        return self::SUCCESS;
    }
}
