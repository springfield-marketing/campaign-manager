<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteClient extends Command
{
    protected $signature = 'clients:delete {id : Client ID to delete}
                            {--reason= : Why this client is being deleted}
                            {--apply : Actually delete (default is dry run)}
                            {--force : Allow deleting a client with real campaign activity (call/message history)}';

    protected $description = 'Delete a client (and its phone numbers + sources), logging a full snapshot first so the action is auditable';

    /** Above this many IVR calls or WhatsApp messages, refuse unless --force (likely a real contact, not corrupted noise) */
    private const ACTIVITY_GUARD_THRESHOLD = 50;

    public function handle(): int
    {
        $clientId = (int) $this->argument('id');
        $client = Client::find($clientId);

        if (! $client) {
            $this->error("Client #{$clientId} not found.");
            return self::FAILURE;
        }

        $phoneIds = DB::table('client_phone_numbers')->where('client_id', $clientId)->pluck('id');
        $callCount = DB::table('ivr_call_records')->whereIn('client_phone_number_id', $phoneIds)->count();
        $messageCount = DB::table('whatsapp_messages')->whereIn('client_phone_number_id', $phoneIds)->count();
        $sourceCount = DB::table('client_sources')->where('client_id', $clientId)->count();

        $this->table(['Field', 'Value'], [
            ['Client ID', $clientId],
            ['Name', $client->full_name],
            ['Alternate names', count($client->alternate_names ?? [])],
            ['Phone numbers', $phoneIds->count()],
            ['Client sources', $sourceCount],
            ['IVR call records', $callCount],
            ['WhatsApp messages', $messageCount],
        ]);

        if (($callCount > self::ACTIVITY_GUARD_THRESHOLD || $messageCount > self::ACTIVITY_GUARD_THRESHOLD) && ! $this->option('force')) {
            $this->error(
                "This client has {$callCount} call(s) and {$messageCount} message(s) — that's real campaign activity, ".
                'not obviously corrupted/placeholder noise. Re-run with --force if you are sure.'
            );
            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('Dry run only. Re-run with --apply to actually delete.');
            return self::SUCCESS;
        }

        $reason = $this->option('reason') ?: $this->ask('Reason for deletion (stored in the audit log)');

        DB::transaction(function () use ($client, $clientId, $reason) {
            $snapshot = [
                'client' => $client->toArray(),
                'phone_numbers' => DB::table('client_phone_numbers')->where('client_id', $clientId)->get()->toArray(),
                'sources' => DB::table('client_sources')->where('client_id', $clientId)->get()->toArray(),
                'emails' => DB::table('client_emails')->where('client_id', $clientId)->get()->toArray(),
                'interactions' => DB::table('client_interactions')->where('client_id', $clientId)->get()->toArray(),
                'ownerships' => DB::table('ownerships')->where('client_id', $clientId)->get()->toArray(),
                'tags' => DB::table('client_tags')->where('client_id', $clientId)->get()->toArray(),
            ];

            ClientAuditLog::create([
                'action' => 'deleted',
                'client_id' => $clientId,
                'reason' => $reason,
                'performed_by' => get_current_user() ?: 'console',
                'snapshot' => $snapshot,
            ]);

            $phoneIds = DB::table('client_phone_numbers')->where('client_id', $clientId)->pluck('id');
            DB::table('client_sources')->where('client_id', $clientId)->orWhereIn('client_phone_number_id', $phoneIds)->delete();
            DB::table('client_phone_numbers')->where('client_id', $clientId)->delete();
            DB::table('clients')->where('id', $clientId)->delete();
        });

        $this->info("Client #{$clientId} deleted. Snapshot logged to client_audit_logs.");

        return self::SUCCESS;
    }
}
