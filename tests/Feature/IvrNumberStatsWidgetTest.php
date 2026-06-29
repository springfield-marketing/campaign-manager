<?php

namespace Tests\Feature;

use App\Filament\Resources\IvrNumbers\Pages\ListIvrNumbers;
use App\Filament\Widgets\IvrNumberStatsWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrNumberStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_matching_count_alongside_the_global_stats(): void
    {
        // The filtered "Matching filters" card now lives in the same widget/grid as the
        // global totals, instead of standing apart in its own widget.
        $this->actingAs(User::factory()->create());

        Livewire::test(IvrNumberStatsWidget::class)
            ->assertOk()
            ->assertSee('Total IVR Numbers')
            ->assertSee('Matching filters');
    }

    #[Test]
    public function the_list_page_forwards_filter_state_to_its_widgets(): void
    {
        // ExposesTableToWidgets must stay on the page, or the "Matching filters" stat never
        // receives filter changes and is stuck on the unfiltered total.
        $this->actingAs(User::factory()->create());

        $data = Livewire::test(ListIvrNumbers::class)->instance()->getWidgetData();

        $this->assertArrayHasKey('tableFilters', $data);
        $this->assertArrayHasKey('tableSearch', $data);
    }
}
