<?php

namespace App\Console\Commands;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportRawContacts extends Command
{
    protected $signature = 'ivr:import:raw
                            {path : Absolute path to the CSV file}
                            {--source= : Source name label (e.g. "Binghatti 2026")}';

    protected $description = 'Queue a raw contacts CSV for import, bypassing the web upload size limit';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error("File is not readable: {$path}");
            return self::FAILURE;
        }

        $originalName = basename($path);
        $sizeMb       = round(filesize($path) / 1024 / 1024, 1);
        $targetPath   = 'ivr/imports/raw/' . $originalName;

        $this->info("File : {$originalName} ({$sizeMb} MB)");
        $this->info("Target: storage/app/private/{$targetPath}");

        // Block re-import of an already completed/processing file
        $blocked = IvrImport::query()
            ->where('type', IvrImportType::RawContacts->value)
            ->where('original_file_name', $originalName)
            ->whereNull('reverted_at')
            ->whereNotIn('status', [IvrImportStatus::Failed->value])
            ->exists();

        if ($blocked) {
            $this->error("An import for \"{$originalName}\" already exists and is not failed.");
            $this->line('Use ivr:reprocess --ids=<id> to re-run it, or delete the import record first.');
            return self::FAILURE;
        }

        // Copy into managed storage unless it's already there
        $absoluteTarget = storage_path('app/private/' . $targetPath);

        if (realpath($path) !== realpath($absoluteTarget)) {
            $this->line('Copying file into storage…');

            if (! is_dir(dirname($absoluteTarget))) {
                mkdir(dirname($absoluteTarget), 0755, true);
            }

            if (! copy($path, $absoluteTarget)) {
                $this->error('Failed to copy file. Check permissions.');
                return self::FAILURE;
            }

            $this->info('Copied.');
        } else {
            $this->line('File is already in storage — skipping copy.');
        }

        $sourceName = $this->option('source') ?: null;

        $import = IvrImport::create([
            'type'               => IvrImportType::RawContacts,
            'status'             => IvrImportStatus::Pending,
            'original_file_name' => $originalName,
            'stored_file_name'   => $originalName,
            'storage_path'       => $targetPath,
            'source_name'        => $sourceName,
            'uploaded_by'        => null,
        ]);

        ProcessRawIvrImport::dispatch($import->id)->onQueue('imports');

        $this->info("Import #{$import->id} queued on the [imports] queue.");
        $this->line("Monitor progress at: /admin/import-stagings");

        return self::SUCCESS;
    }
}
