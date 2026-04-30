<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModulePagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_users_can_view_module_pages(): void
    {
        $user = User::factory()->create();

        $routes = [
            route('modules.ivr.index'),
            route('modules.whatsapp.index'),
            route('modules.emails.index'),
            route('modules.ivr.imports.index'),
            route('modules.ivr.results.index'),
            route('modules.ivr.numbers.index'),
            route('modules.ivr.reports.index'),
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)
                ->get($route)
                ->assertOk();
        }
    }
}
