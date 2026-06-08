<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessRawIvrImport;
use App\Modules\IVR\Models\IvrImport;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Storage;

class RawContactImportProgressWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '3s';

    protected string $view = 'filament.widgets.raw-contact-import-progress';

    protected function getViewData(): array
    {
        $imports = IvrImport::query()
            ->where('type', IvrImportType::RawContacts)
            ->latest()
            ->limit(10)
            ->get();

        return compact('imports');
    }

    public function retryImport(int $importId): void
    {
        $import = IvrImport::find($importId);

        if (! $import || $import->type !== IvrImportType::RawContacts->value) {
            return;
        }

        if (! in_array($import->status, [IvrImportStatus::Failed->value, IvrImportStatus::CompletedWithErrors->value], true)) {
            Notification::make()->title('Only failed imports can be retried.')->warning()->send();
            return;
        }

        if (! $import->storage_path || ! Storage::disk('local')->exists($import->storage_path)) {
            Notification::make()
                ->title('File is no longer on disk.')
                ->body('Please upload the file again using the Upload button.')
                ->danger()
                ->send();
            return;
        }

        $import->update([
            'status'          => IvrImportStatus::Pending,
            'error_message'   => null,
            'total_rows'      => 0,
            'processed_rows'  => 0,
            'successful_rows' => 0,
            'failed_rows'     => 0,
            'duplicate_rows'  => 0,
            'started_at'      => null,
            'completed_at'    => null,
            'summary'         => array_merge(
                is_array($import->summary) ? $import->summary : [],
                ['staged_rows' => 0, 'staging_batch_id' => null],
            ),
        ]);

        $import->broadcastProgress();
        ProcessRawIvrImport::dispatch($import->id)->onQueue('imports');

        Notification::make()
            ->title('Import requeued.')
            ->body('Watch the status card update.')
            ->success()
            ->send();
    }
}
