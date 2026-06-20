<?php

namespace Tests\Feature;

use App\Filament\Pages\WhatsAppFailureAnalysisPage;
use App\Filament\Pages\WhatsAppTemplatePerformancePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppAnalyticsPagesTest extends TestCase
{
    use RefreshDatabase;

    // loadTable() triggers the deferred table query — without it the page HTML renders fine but
    // the grouped/paginated query (the part that previously failed the GROUP BY) is never run.

    #[Test]
    public function the_template_performance_table_loads(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(WhatsAppTemplatePerformancePage::class)
            ->loadTable()
            ->assertOk();
    }

    #[Test]
    public function the_failure_analysis_table_loads(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(WhatsAppFailureAnalysisPage::class)
            ->loadTable()
            ->assertOk();
    }
}
