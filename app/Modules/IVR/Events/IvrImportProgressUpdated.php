<?php

namespace App\Modules\IVR\Events;

use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\IvrImportStatusPayload;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IvrImportProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public array $import;

    public function __construct(IvrImport $import)
    {
        $this->import = IvrImportStatusPayload::make($import->refresh());
    }

    public function broadcastOn(): Channel
    {
        return new Channel('ivr.imports');
    }

    public function broadcastAs(): string
    {
        return 'ivr.import.updated';
    }
}
