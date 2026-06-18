<?php

namespace App\Console\Commands;

use App\Models\ClientAuditLog;
use App\Modules\IVR\Support\PhoneNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SplFileObject;

/**
 * Backfill clients.emirate from a phone CSV. For every phone in the CSV (column 0) that matches a
 * number in the DB, set the owning client's emirate — but ONLY when the client has no emirate yet
 * (missing-only; never overwrites an emirate already on record). Writes a backup CSV of every
 * affected client and a client_audit_logs summary row so the change is reviewable and reversible.
 */
class SetEmirateFromCsv extends Command
{
    protected $signature = 'clients:set-emirate-from-csv
                            {csv : Path to a CSV whose first column is the phone number}
                            {emirate : Emirate to set (e.g. Dubai)}
                            {--apply : Actually update (default is a dry run)}';

    protected $description = 'Backfill clients.emirate (missing-only) from the phone numbers in a CSV';

    public function handle(PhoneNormalizer $normalizer): int
    {
        ini_set('memory_limit', '2G');

        $csvPath = $this->argument('csv');
        $emirate = trim((string) $this->argument('emirate'));

        if (! is_file($csvPath)) {
            $this->error("CSV not found: {$csvPath}");

            return self::FAILURE;
        }
        if ($emirate === '') {
            $this->error('Emirate must not be empty.');

            return self::FAILURE;
        }

        // 1. Normalise every phone in the CSV to canonical candidates.
        $this->info('Reading CSV…');
        $file = new SplFileObject($csvPath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);

        $candidates = [];
        $rows = 0;
        foreach ($file as $row) {
            if (! is_array($row) || ! isset($row[0])) {
                continue;
            }
            $phone = trim((string) $row[0]);
            if ($phone === '' || strtolower($phone) === 'phone') {
                continue;
            }
            $rows++;
            try {
                $candidates[$normalizer->normalize($phone)['normalized']] = true;
            } catch (\Throwable) {
                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                if ($digits !== '') {
                    $candidates['+'.$digits] = true;
                }
            }
        }
        $candidates = array_keys($candidates);
        $this->info(number_format($rows).' phone rows, '.number_format(count($candidates)).' distinct numbers.');

        // 2. Match to clients.
        $clientIds = [];
        foreach (array_chunk($candidates, 5000) as $chunk) {
            foreach (DB::table('client_phone_numbers')->whereIn('normalized_phone', $chunk)->whereNotNull('client_id')->pluck('client_id') as $cid) {
                $clientIds[$cid] = true;
            }
        }
        $clientIds = array_keys($clientIds);
        $this->info(number_format(count($clientIds)).' distinct clients matched.');

        // 3. Of those, the ones with a missing emirate (the update targets).
        $targets = [];
        foreach (array_chunk($clientIds, 5000) as $chunk) {
            foreach (DB::table('clients')->whereIn('id', $chunk)
                ->where(fn ($q) => $q->whereNull('emirate')->orWhereRaw("trim(emirate) = ''"))
                ->pluck('id') as $id) {
                $targets[] = $id;
            }
        }
        $this->info(number_format(count($targets))." clients have a missing emirate -> would be set to \"{$emirate}\".");

        if ($targets === []) {
            $this->info('Nothing to update.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply to update.');

            return self::SUCCESS;
        }

        // 4. Backup CSV of every client being changed (old emirate was blank) — for review/undo.
        $backupRel = 'backups/emirate_backfill_'.now()->format('Ymd_His').'.csv';
        $backupAbs = storage_path('app/'.$backupRel);
        @mkdir(dirname($backupAbs), 0775, true);
        $handle = fopen($backupAbs, 'w');
        fputcsv($handle, ['client_id', 'old_emirate', 'new_emirate']);
        foreach ($targets as $id) {
            fputcsv($handle, [$id, '', $emirate]);
        }
        fclose($handle);
        $this->info("Backup written: {$backupAbs}");

        // 5. Update (missing-only guard repeated for safety) + audit log, in one transaction.
        $updated = 0;
        DB::transaction(function () use ($targets, $emirate, $backupRel, $csvPath, &$updated): void {
            foreach (array_chunk($targets, 5000) as $chunk) {
                $updated += DB::table('clients')->whereIn('id', $chunk)
                    ->where(fn ($q) => $q->whereNull('emirate')->orWhereRaw("trim(emirate) = ''"))
                    ->update(['emirate' => $emirate, 'updated_at' => now()]);
            }

            ClientAuditLog::create([
                'action'       => 'emirate_backfilled',
                'client_id'    => 0,
                'reason'       => "Set emirate = \"{$emirate}\" on clients matched from ".basename($csvPath)
                    .' that had no emirate. Missing-only (existing emirates untouched). Authorized by user.',
                'performed_by' => get_current_user() ?: 'console',
                'snapshot'     => [
                    'source_file'     => basename($csvPath),
                    'emirate'         => $emirate,
                    'clients_updated' => $updated,
                    'backup_file'     => $backupRel,
                    'sample_ids'      => array_slice($targets, 0, 50),
                ],
            ]);
        });

        $this->info(number_format($updated)." clients updated to \"{$emirate}\". Logged to client_audit_logs (action: emirate_backfilled).");

        return self::SUCCESS;
    }
}
