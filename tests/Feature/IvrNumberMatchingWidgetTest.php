<?php

namespace Tests\Feature;

use App\Filament\Resources\IvrNumbers\Pages\ListIvrNumbers;
use App\Filament\Widgets\IvrNumberMatchingWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrNumberMatchingWidgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_matching_count(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(IvrNumberMatchingWidget::class)
            ->assertOk()
            ->assertSee('Matching filters');
    }

    #[Test]
    public function the_list_page_forwards_filter_state_to_its_widgets(): void
    {
        // ExposesTableToWidgets must be on the page, or the widget's reactive props never receive
        // filter changes and the count is stuck on the unfiltered total.
        $this->actingAs(User::factory()->create());

        $data = Livewire::test(ListIvrNumbers::class)->instance()->getWidgetData();

        $this->assertArrayHasKey('tableFilters', $data);
        $this->assertArrayHasKey('tableSearch', $data);
    }
}
