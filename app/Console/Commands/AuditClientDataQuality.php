<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Modules\IVR\Support\PhoneNormalizer;
use App\Support\RawContactImportEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditClientDataQuality extends Command
{
    protected $signature = 'clients:audit-data-quality
                            {--threshold=20 : Minimum alternate_names count to flag a client for review}
                            {--phone-threshold=5 : Minimum phone-number count to flag a stub-named client (IMP-001)}';

    protected $description = 'Profile clients for signs of bad phone-number merges (placeholder/garbage numbers absorbing many unrelated leads)';

    public function handle(PhoneNormalizer $phoneNormalizer): int
    {
        $this->auditStubNameMultiNumber((int) $this->option('phone-threshold'));

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

    /**
     * IMP-001: a stub/placeholder name (e.g. "Instagram Dm", "No Name") with several distinct
     * phone numbers attached is the signature of unrelated leads merged onto one client. The
     * import fix (RawContactImportEnricher) stops this going forward; this flags the historical
     * residue and anything that still slips through. See docs/data-rules/imports.md.
     */
    private function auditStubNameMultiNumber(int $phoneThreshold): void
    {
        $candidates = DB::select('
            SELECT c.id, c.full_name,
                   count(cpn.id)                  AS phone_count,
                   count(DISTINCT cpn.country_code) AS country_codes
            FROM clients c
            JOIN client_phone_numbers cpn ON cpn.client_id = c.id
            GROUP BY c.id, c.full_name
            HAVING count(cpn.id) >= ?
            ORDER BY phone_count DESC
            LIMIT 500
        ', [$phoneThreshold]);

        $flagged = array_values(array_filter(
            $candidates,
            fn ($c) => RawContactImportEnricher::isStubName((string) $c->full_name),
        ));

        if ($flagged === []) {
            $this->info("IMP-001: no stub-named clients with {$phoneThreshold}+ phone numbers. ✓");
            $this->newLine();

            return;
        }

        $this->error(
            count($flagged)." client(s) match the IMP-001 stub-name merge pattern ".
            "({$phoneThreshold}+ numbers under a placeholder name — likely unrelated leads merged together):"
        );
        $this->table(
            ['Client ID', 'Name', 'Phone Count', 'Distinct Country Codes'],
            array_map(fn ($c) => [$c->id, $c->full_name, $c->phone_count, $c->country_codes], $flagged),
        );
        $this->warn('Remediation: review, then run `php artisan clients:split-name-collisions --apply` to split them back into one client per phone. See docs/data-rules/imports.md.');
        $this->newLine();
    }
}
