<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    #[Test]
    public function a_user_without_2fa_can_reach_the_panel_and_profile(): void
    {
        // Optional 2FA must not lock out a user who hasn't enrolled.
        $this->actingAs(User::factory()->create());

        $this->get('/admin/profile')->assertOk();
    }
}
