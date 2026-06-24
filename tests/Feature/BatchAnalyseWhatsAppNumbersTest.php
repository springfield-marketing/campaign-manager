<?php

namespace Tests\Feature;

use App\Modules\WhatsApp\Jobs\BatchAnalyseWhatsAppNumbers;
use App\Modules\WhatsApp\Models\WhatsAppSettings;
use App\Modules\WhatsApp\Support\WhatsAppBatchProfileUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BatchAnalyseWhatsAppNumbersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tracked_reanalysis_completes_and_records_a_non_negative_duration(): void
    {
        // Regression: Carbon 3 diffInSeconds returned a signed/fractional value that was written
        // into the integer last_run_duration_seconds column, so the job failed at the very end
        // (after the work was done) with "invalid input syntax for type integer".
        WhatsAppSettings::current()->update(['reanalysis_status' => null]);

        (new BatchAnalyseWhatsAppNumbers([], trackProgress: true))
            ->handle(app(WhatsAppBatchProfileUpdater::class));

        $settings = WhatsAppSettings::current()->fresh();

        $this->assertSame('completed', $settings->reanalysis_status);
        $this->assertNotNull($settings->reanalysis_completed_at);
        $this->assertIsInt($settings->last_run_duration_seconds);
        $this->assertGreaterThanOrEqual(0, $settings->last_run_duration_seconds);
    }
}
