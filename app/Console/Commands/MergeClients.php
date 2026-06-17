<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeClients extends Command
{
    protected $signature = 'clients:merge {from : Client ID to merge away (absorbed)} {into : Client ID to keep}
                            {--reason= : Why these clients are being merged}
                            {--apply : Actually merge (default is dry run)}';

    protected $description = 'Merge one client into another (sources, emails, interactions, ownerships, tags, alternate_names), logging a snapshot first';

    public function handle(): int
    {
        $fromId = (int) $this->argument('from');
        $intoId = (int) $this->argument('into');

        if ($fromId === $intoId) {
            $this->error('Cannot merge a client into itself.');
            return self::FAILURE;
        }

        $from = Client::find($fromId);
        $into = Client::find($intoId);

        if (! $from || ! $into) {
            $this->error('Both clients must exist.');
            return self::FAILURE;
        }

        $this->table(['Field', 'From (absorbed)', 'Into (kept)'], [
            ['Client ID', $fromId, $intoId],
            ['Name', $from->full_name, $into->full_name],
            ['Alternate names', count($from->alternate_names ?? []), count($into->alternate_names ?? [])],
            ['Phone numbers', DB::table('client_phone_numbers')->where('client_id', $fromId)->count(), DB::table('client_phone_numbers')->where('client_id', $intoId)->count()],
            ['Sources', DB::table('client_sources')->where('client_id', $fromId)->count(), DB::table('client_sources')->where('client_id', $intoId)->count()],
        ]);

        if (! $this->option('apply')) {
            $this->info('Dry run only. Re-run with --apply to actually merge.');
            return self::SUCCESS;
        }

        $reason = $this->option('reason') ?: $this->ask('Reason for merge (stored in the audit log)');

        DB::transaction(function () use ($from, $into, $fromId, $intoId, $reason) {
            $snapshot = [
                'from_client' => $from->toArray(),
                'into_client' => $into->toArray(),
                'from_phone_numbers' => DB::table('client_phone_numbers')->where('client_id', $fromId)->get()->toArray(),
                'from_source_count' => DB::table('client_sources')->where('client_id', $fromId)->count(),
                'from_email_count' => DB::table('client_emails')->where('client_id', $fromId)->count(),
            ];

            ClientAuditLog::create([
                'action' => 'merged',
                'client_id' => $fromId,
                'target_client_id' => $intoId,
                'reason' => $reason,
                'performed_by' => get_current_user() ?: 'console',
                'snapshot' => $snapshot,
            ]);

            DB::table('client_sources')->where('client_id', $fromId)->update(['client_id' => $intoId]);

            foreach (DB::table('client_emails')->where('client_id', $fromId)->get() as $email) {
                $dupe = DB::table('client_emails')->where('client_id', $intoId)
                    ->whereRaw('lower(email) = ?', [strtolower($email->email)])->exists();
                $dupe
                    ? DB::table('client_emails')->where('id', $email->id)->delete()
                    : DB::table('client_emails')->where('id', $email->id)->update(['client_id' => $intoId, 'is_primary' => false]);
            }

            DB::table('client_interactions')->where('client_id', $fromId)->update(['client_id' => $intoId]);
            DB::table('ownerships')->where('client_id', $fromId)->update(['client_id' => $intoId]);

            foreach (DB::table('client_tags')->where('client_id', $fromId)->get() as $tag) {
                $dupe = DB::table('client_tags')->where('client_id', $intoId)->where('tag_id', $tag->tag_id)->exists();
                $dupe
                    ? DB::table('client_tags')->where('id', $tag->id)->delete()
                    : DB::table('client_tags')->where('id', $tag->id)->update(['client_id' => $intoId]);
            }

            $intoAlt = $into->alternate_names ?? [];
            $fromAlt = $from->alternate_names ?? [];
            $namesToAdd = array_filter(array_merge([$from->full_name], $fromAlt), fn ($n) => $n && $n !== $into->full_name);
            $merged = array_values(array_unique(array_merge($intoAlt, $namesToAdd)));
            DB::table('clients')->where('id', $intoId)->update(['alternate_names' => json_encode($merged), 'updated_at' => now()]);

            DB::table('client_phone_numbers')->where('client_id', $fromId)->delete();
            DB::table('clients')->where('id', $fromId)->delete();
        });

        $this->info("Client #{$fromId} merged into #{$intoId}. Snapshot logged to client_audit_logs.");

        return self::SUCCESS;
    }
}
