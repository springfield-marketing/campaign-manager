<?php

namespace App\Modules\IVR\Jobs;

use App\Modules\IVR\Models\CentralDatabaseExport;
use App\Modules\IVR\Support\CentralDatabaseExcelExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExportCentralDatabase implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $exportId,
    ) {
    }

    public function handle(CentralDatabaseExcelExporter $exporter): void
    {
        $export = CentralDatabaseExport::query()->findOrFail($this->exportId);

        $exporter->export($export);
    }
}
