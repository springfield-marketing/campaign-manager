<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Support\Identity\NameClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlagInstitutionClients extends Command
{
    protected $signature = 'clients:flag-institutions
                            {--apply : Actually update the flag (default is dry run)}';

    protected $description = 'IMP-003: set clients.is_institution from the name classifier (developer/bank/LLC names), so organisation records can be excluded from the contacts list';

    public function handle(): int
    {
        $toTrue = [];
        $toFalse = [];

        Client::query()
            ->select('id', 'full_name', 'is_institution')
            ->orderBy('id')
            ->chunkById(2000, function ($clients) use (&$toTrue, &$toFalse): void {
                foreach ($clients as $client) {
                    $shouldFlag = $client->full_name !== null
                        && $client->full_name !== ''
                        && NameClassifier::isInstitution($client->full_name);

                    if ($shouldFlag && ! $client->is_institution) {
                        $toTrue[] = $client->id;
                    } elseif (! $shouldFlag && $client->is_institution) {
                        $toFalse[] = $client->id;
                    }
                }
            });

        $this->info(count($toTrue).' client(s) to flag as institutions; '.count($toFalse).' to unflag.');

        if (! $this->option('apply')) {
            $this->line('Dry run only. Re-run with --apply to write the flag.');

            return self::SUCCESS;
        }

        foreach (array_chunk($toTrue, 1000) as $batch) {
            DB::table('clients')->whereIn('id', $batch)->update(['is_institution' => true]);
        }
        foreach (array_chunk($toFalse, 1000) as $batch) {
            DB::table('clients')->whereIn('id', $batch)->update(['is_institution' => false]);
        }

        $this->info('Done. Flagged '.count($toTrue).', unflagged '.count($toFalse).'.');

        return self::SUCCESS;
    }
}
