<?php

namespace App\Console\Commands;

use App\Modules\WhatsApp\Support\WhatsAppNumberAnalyser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReanalyseWhatsAppNumbers extends Command
{
    protected $signature = 'whatsapp:reanalyse-numbers
                            {--chunk=500 : Number of numbers to process per batch}';

    protected $description = 'Re-run the WhatsApp number analyser on all numbers that have messages, rebuilding usage_status, cooldown_until, and consecutive_hard_fail_count.';

    public function handle(WhatsAppNumberAnalyser $analyser): int
    {
        $total = DB::table('whatsapp_messages')
            ->whereNotNull('client_phone_number_id')
            ->distinct('client_phone_number_id')
            ->count('client_phone_number_id');

        if ($total === 0) {
            $this->info('No WhatsApp numbers to process.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} numbers…");
        $bar  = $this->output->createProgressBar($total);
        $bar->start();

        $chunk  = (int) $this->option('chunk');
        $done   = 0;
        $lastId = 0;

        do {
            $ids = DB::table('whatsapp_messages')
                ->whereNotNull('client_phone_number_id')
                ->where('client_phone_number_id', '>', $lastId)
                ->groupBy('client_phone_number_id')
                ->orderBy('client_phone_number_id')
                ->limit($chunk)
                ->pluck('client_phone_number_id');

            foreach ($ids as $id) {
                $analyser->analyse($id);
                $bar->advance();
                $done++;
                $lastId = $id;
            }
        } while ($ids->count() === $chunk);

        $bar->finish();
        $this->newLine();
        $this->info("Done. Processed {$done} numbers.");

        return self::SUCCESS;
    }
}
