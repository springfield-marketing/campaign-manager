<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\IvrNumbers\Pages\ListIvrNumbers;
use App\Filament\Resources\WhatsAppNumbers\Pages\ListWhatsAppNumbers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BigListPagesTest extends TestCase
{
    use RefreshDatabase;

    // loadTable() runs the deferred, simple-paginated query — guards the high-volume list pages.

    #[Test]
    public function the_contacts_list_loads(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ListClients::class)->loadTable()->assertOk();
    }

    #[Test]
    public function the_ivr_numbers_list_loads(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ListIvrNumbers::class)->loadTable()->assertOk();
    }

    #[Test]
    public function the_whatsapp_numbers_list_loads(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ListWhatsAppNumbers::class)->loadTable()->assertOk();
    }
}
