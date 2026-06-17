<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Modules\IVR\Support\PhoneNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditClientDataQuality extends Command
{
    protected $signature = 'clients:audit-data-quality
                            {--threshold=20 : Minimum alternate_names count to flag a client for review}';

    protected $description = 'Profile clients for signs of bad phone-number merges (placeholder/garbage numbers absorbing many unrelated leads)';

    public function handle(PhoneNormalizer $phoneNormalizer): int
    {
        $threshold = (int) $this->option('threshold');

        $clients = Client::query()
            ->whereNotNull('alternate_names')
            ->get(['id', 'full_name', 'alternate_names'])
            ->filter(fn (Client $c) => is_array($c->alternate_names) && count($c->alternate_names) >= $threshold)
            ->sortByDesc(fn (Client $c) => count($c->alternate_names));

        if ($clients->isEmpty()) {
            $this->info("No clients found with {$threshold}+ alternate names.");

            return self::SUCCESS;
        }

        $likelyCorrupted = [];
        $likelySharedLine = [];
        $knownSharedLine = [];

        foreach ($clients as $client) {
            $phone = $client->phoneNumbers()->first();
            $rawPhone = $phone?->raw_phone ?? '';
            $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
            $sourceCount = DB::table('client_sources')->where('client_id', $client->id)->count();

            $row = [
                $client->id,
                $client->full_name,
                $rawPhone ?: '—',
                count($client->alternate_names),
                $sourceCount,
            ];

            if ($phone?->is_shared_line) {
                $row[] = $phone->shared_line_note ?: '—';
                $knownSharedLine[] = $row;
            } elseif ($digits !== '' && $phoneNormalizer->looksLikePlaceholder($digits)) {
                $likelyCorrupted[] = $row;
            } else {
                $likelySharedLine[] = $row;
            }
        }

        if ($likelyCorrupted !== []) {
            $this->error(count($likelyCorrupted).' client(s) look CORRUPTED (placeholder/garbage phone number absorbing unrelated leads):');
            $this->table(['Client ID', 'Name', 'Phone', 'Alt Names', 'Sources'], $likelyCorrupted);
            $this->newLine();
        }

        if ($likelySharedLine !== []) {
            $this->warn(count($likelySharedLine).' client(s) have many alternate names but a normal-looking phone number (likely a shared hotline/reception line — review manually, then run clients:mark-shared-line to silence this once confirmed):');
            $this->table(['Client ID', 'Name', 'Phone', 'Alt Names', 'Sources'], $likelySharedLine);
            $this->newLine();
        }

        if ($knownSharedLine !== []) {
            $this->line(count($knownSharedLine).' client(s) are already documented as known shared lines (no action needed):');
            $this->table(['Client ID', 'Name', 'Phone', 'Alt Names', 'Sources', 'Note'], $knownSharedLine);
        }

        return self::SUCCESS;
    }
}
