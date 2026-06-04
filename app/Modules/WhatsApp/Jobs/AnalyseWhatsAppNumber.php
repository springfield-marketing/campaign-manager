<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Support\WhatsAppNumberAnalyser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyseWhatsAppNumber implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $phoneNumberId) {}

    public function handle(WhatsAppNumberAnalyser $analyser): void
    {
        $analyser->analyse($this->phoneNumberId);
    }
}
