<?php

namespace Tests\Feature;

use App\Filament\Widgets\IvrNumberStatsWidget;
use App\Filament\Widgets\WhatsAppNumberStatsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarmNumberStatsCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_populates_both_stat_caches_with_the_totals(): void
    {
        Cache::forget(IvrNumberStatsWidget::CACHE_KEY);
        Cache::forget(WhatsAppNumberStatsWidget::CACHE_KEY);

        $this->artisan('stats:warm-number-widgets')->assertSuccessful();

        $ivr = Cache::get(IvrNumberStatsWidget::CACHE_KEY);
        $whatsapp = Cache::get(WhatsAppNumberStatsWidget::CACHE_KEY);

        // The widgets read these keys; they must be the array shape getStats() expects.
        $this->assertIsArray($ivr);
        $this->assertArrayHasKey('total', $ivr);
        $this->assertIsArray($whatsapp);
        $this->assertArrayHasKey('total', $whatsapp);
    }
}
