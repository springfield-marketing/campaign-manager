<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Models\User;
use App\Modules\IVR\Models\IvrCallRecord;
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
            route('modules.ivr.unsubscribers.index'),
            route('modules.ivr.reports.index'),
            route('modules.ivr.settings.edit'),
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)
                ->get($route)
                ->assertOk();
        }
    }

    #[Test]
    public function numbers_page_supports_source_and_use_count_filters(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['full_name' => 'Named Filter Client']);

        $number = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000001',
            'normalized_phone' => '+971500000001',
            'is_uae' => true,
            'usage_status' => 'active',
        ]);

        ClientSource::create([
            'client_phone_number_id' => $number->id,
            'channel' => 'ivr',
            'source_type' => 'raw_import',
            'source_name' => 'AL Reem Island',
            'source_reference' => 'test',
        ]);

        IvrCallRecord::create([
            'client_phone_number_id' => $number->id,
            'external_call_uuid' => 'test-call-1',
        ]);

        $this->actingAs($user)
            ->get(route('modules.ivr.numbers.index', [
                'source_include' => ['AL Reem Island'],
                'phone' => '500000001',
                'uses_max' => 3,
            ]))
            ->assertOk()
            ->assertSee('+971500000001');
    }
}
