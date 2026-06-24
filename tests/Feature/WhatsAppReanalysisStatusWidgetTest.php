<?php

namespace Tests\Feature;

use App\Filament\Widgets\WhatsAppReanalysisStatusWidget;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppReanalysisStatusWidgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_while_a_reanalysis_is_running_with_an_estimate(): void
    {
        // Regression: Carbon 3 diffInSeconds is signed, so elapsed went negative and the progress
        // bar's str_repeat() threw "Argument #2 must be >= 0".
        $this->actingAs(User::factory()->create());

        WhatsAppSettings::current()->update([
            'reanalysis_status' => 'running',
            'reanalysis_started_at' => now()->subSeconds(30),
            'last_run_duration_seconds' => 100,
        ]);

        Livewire::test(WhatsAppReanalysisStatusWidget::class)->assertOk();
    }
}
