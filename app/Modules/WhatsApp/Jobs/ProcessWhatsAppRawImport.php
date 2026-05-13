<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppRawImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppRawImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;
    public int $tries   = 1;
    public bool $failOnTimeout = true;

    public function __construct(private readonly int $importId) {}

    public function handle(WhatsAppRawImportProcessor $processor): void
    {
        $import = WhatsAppImport::findOrFail($this->importId);
        $processor->process($import);
    }
}
