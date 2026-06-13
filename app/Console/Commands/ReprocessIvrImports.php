<?php

namespace App\Console\Commands;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Jobs\ProcessIvrCampaignResultsImport;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Jobs\ProcessUnsubscriberImport;
use App\Modules\IVR\Models\IvrImport;
use Illuminate\Console\Command;

class ReprocessIvrImports extends Command
{
    protected $signature = 'ivr:reprocess
                            {type : raw_contacts | campaign_results | unsubscribers | all}
                            {--dry-run : Show what would be queued without actually dispatching}
                            {--ids= : Comma-separated import IDs to limit reprocessing}';

    protected $description = 'Re-queue completed IVR imports so their contacts/records are recreated in the new schema';

    public function handle(): int
    {
        $type   = $this->argument('type');
        $dryRun = $this->option('dry-run');
        $ids    = $this->option('ids')
            ? array_map('intval', explode(',', $this->option('ids')))
            : [];

        $types = $type === 'all'
            ? ['raw_contacts', 'campaign_results', 'unsubscribers']
            : [$type];

        foreach ($types as $t) {
            $this->reprocessType($t, $dryRun, $ids);
        }

        return self::SUCCESS;
    }

    private function reprocessType(string $type, bool $dryRun, array $ids): void
    {
        $query = IvrImport::query()
            ->where('type', $type)
            ->whereIn('status', [
                IvrImportStatus::Completed->value,
                IvrImportStatus::CompletedWithErrors->value,
                IvrImportStatus::Failed->value,
            ])
            ->whereNull('reverted_at');

        if ($ids) {
            $query->whereIn('id', $ids);
        }

        $imports = $query->orderBy('id')->get();

        $this->info("Type [{$type}]: {$imports->count()} import(s) found.");

        if ($imports->isEmpty()) {
            return;
        }

        // Verify files exist on disk before queuing
        $missing = $imports->filter(fn ($i) =>
            ! $i->storage_path || ! file_exists(storage_path('app/private/' . $i->storage_path))
        );

        if ($missing->isNotEmpty()) {
            $this->warn("  {$missing->count()} file(s) missing from disk — will skip:");
            $missing->each(fn ($i) => $this->line("    ID {$i->id}: {$i->original_file_name}"));
        }

        $toProcess = $imports->filter(fn ($i) =>
            $i->storage_path && file_exists(storage_path('app/private/' . $i->storage_path))
        );

        $this->line("  {$toProcess->count()} file(s) on disk, ready to queue.");

        if ($dryRun) {
            $toProcess->each(fn ($i) => $this->line("  [DRY RUN] Would queue: ID {$i->id} — {$i->original_file_name}"));
            return;
        }

        $bar = $this->output->createProgressBar($toProcess->count());
        $bar->start();

        foreach ($toProcess as $import) {
            // Reset to pending so the processor re-runs
            $import->errors()->delete();
            $import->update([
                'status'         => IvrImportStatus::Pending->value,
                'error_message'  => null,
                'started_at'     => null,
                'completed_at'   => null,
            ]);

            match($type) {
                'raw_contacts'     => ProcessRawIvrImport::dispatch($import->id)->onQueue('imports'),
                'campaign_results' => ProcessIvrCampaignResultsImport::dispatch($import->id)->onQueue('imports'),
                'unsubscribers'    => ProcessUnsubscriberImport::dispatch($import->id)->onQueue('imports'),
            };

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Queued {$toProcess->count()} job(s) onto the [imports] queue.");
    }
}
