<?php

namespace App\Console\Commands;

use App\Models\WhatsAppExportBatch;
use Illuminate\Console\Command;

class PruneWhatsAppExportBatches extends Command
{
    protected $signature = 'whatsapp:prune-export-batches
                            {--days=7 : Delete export batches older than this many days}';

    protected $description = 'Delete WhatsApp export batches (and their numbers) older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        // Export batches exist only to dedupe back-to-back exports (so a second export can exclude
        // the first export's numbers). After the retention window they're no longer needed. The
        // whatsapp_export_batch_numbers pivot is removed via ON DELETE CASCADE when the parent batch
        // goes, so deleting old batches frees those number rows too — that's what stops the pivot
        // from growing forever.
        $deleted = WhatsAppExportBatch::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} WhatsApp export batch(es) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
