<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\IvrNumbers\IvrNumberResource;
use App\Filament\Resources\RawImports\RawImportResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WhatsAppNumbers\WhatsAppNumberResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }

    #[Test]
    public function admin_sees_every_module(): void
    {
        $this->actingAsRole(UserRole::Admin);

        $this->assertTrue(IvrNumberResource::canAccess());
        $this->assertTrue(WhatsAppNumberResource::canAccess());
        $this->assertTrue(RawImportResource::canAccess());
        $this->assertTrue(UserResource::canAccess());
        $this->assertTrue(ClientResource::canAccess());
    }

    #[Test]
    public function ivr_user_sees_ivr_and_contacts_but_not_whatsapp_or_admin_tools(): void
    {
        $this->actingAsRole(UserRole::Ivr);

        $this->assertTrue(IvrNumberResource::canAccess());
        $this->assertTrue(ClientResource::canAccess());        // Contacts are open to all roles
        $this->assertFalse(WhatsAppNumberResource::canAccess());
        $this->assertFalse(RawImportResource::canAccess());    // admin only
        $this->assertFalse(UserResource::canAccess());         // admin only
    }

    #[Test]
    public function whatsapp_user_sees_whatsapp_and_contacts_but_not_ivr_or_admin_tools(): void
    {
        $this->actingAsRole(UserRole::WhatsApp);

        $this->assertTrue(WhatsAppNumberResource::canAccess());
        $this->assertTrue(ClientResource::canAccess());
        $this->assertFalse(IvrNumberResource::canAccess());
        $this->assertFalse(UserResource::canAccess());
    }

    #[Test]
    public function role_helpers_resolve_correctly(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $ivr = User::factory()->create(['role' => UserRole::Ivr]);
        $wa = User::factory()->create(['role' => UserRole::WhatsApp]);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->canAccessIvr());
        $this->assertTrue($admin->canAccessWhatsApp());

        $this->assertFalse($ivr->isAdmin());
        $this->assertTrue($ivr->canAccessIvr());
        $this->assertFalse($ivr->canAccessWhatsApp());

        $this->assertFalse($wa->canAccessIvr());
        $this->assertTrue($wa->canAccessWhatsApp());
    }
}
