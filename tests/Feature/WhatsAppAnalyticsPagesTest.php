<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppAnalyticsPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_template_performance_page_renders(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin/whatsapp-template-performance')->assertOk();
    }

    #[Test]
    public function the_failure_analysis_page_renders(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin/whatsapp-failure-analysis')->assertOk();
    }
}
