<?php

namespace App\Console\Commands;

use App\Models\ClientPhoneNumber;
use Illuminate\Console\Command;

class MarkSharedLine extends Command
{
    protected $signature = 'clients:mark-shared-line {client_id : Client ID whose phone number is a known shared/reception line}
                            {--note= : Why this number is a legitimate shared line (e.g. "Emaar customer service hotline")}
                            {--unmark : Clear the flag instead of setting it}';

    protected $description = 'Flag a phone number as a known legitimate shared line (hotline/reception desk), so the data-quality audit stops re-flagging it';

    public function handle(): int
    {
        $clientId = (int) $this->argument('client_id');
        $phone = ClientPhoneNumber::where('client_id', $clientId)->first();

        if (! $phone) {
            $this->error("No phone number found for client #{$clientId}.");
            return self::FAILURE;
        }

        if ($this->option('unmark')) {
            $phone->forceFill(['is_shared_line' => false, 'shared_line_note' => null])->save();
            $this->info("Cleared shared-line flag for {$phone->normalized_phone} (client #{$clientId}).");
            return self::SUCCESS;
        }

        $note = $this->option('note') ?: $this->ask('Why is this a legitimate shared line?');

        $phone->forceFill(['is_shared_line' => true, 'shared_line_note' => $note])->save();
        $this->info("Marked {$phone->normalized_phone} (client #{$clientId}) as a known shared line.");

        return self::SUCCESS;
    }
}
