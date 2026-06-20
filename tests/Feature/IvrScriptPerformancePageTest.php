<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrScriptPerformancePageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_script_performance_page_renders(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/ivr-script-performance')->assertOk();
    }
}
