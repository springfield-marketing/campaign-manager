<?php

namespace Tests\Feature;

use App\Filament\Widgets\WhatsAppNumberMatchingWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppNumberMatchingWidgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_matching_count(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(WhatsAppNumberMatchingWidget::class)
            ->assertOk()
            ->assertSee('Matching filters');
    }
}
