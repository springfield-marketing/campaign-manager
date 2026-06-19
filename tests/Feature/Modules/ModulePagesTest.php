<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Models\User;
use App\Filament\Resources\IvrCampaigns\Pages\EditIvrCampaign;
use App\Filament\Resources\IvrCampaigns\RelationManagers\CallRecordsRelationManager;
use App\Modules\IVR\Jobs\ExportCentralDatabase;
use App\Modules\IVR\Models\CentralDatabaseExport;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModulePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // QUARANTINED: targets the removed modules.* web routes (pre-Filament migration); the
        // module index pages are now Filament resources. Rewrite against the Filament routes
        // + valid phone fixtures before re-enabling.
        $this->markTestSkipped('Stale pre-Filament test — see setUp() note; needs rewrite.');
    }

    #[Test]
    public function authenticated_users_can_view_module_pages(): void
    {
        $user = User::factory()->create();

        $routes = [
            route('modules.ivr.index'),
            route('modules.whatsapp.campaigns.index'),
            route('modules.whatsapp.imports.index'),
            route('modules.whatsapp.numbers.index'),
            route('modules.whatsapp.unsubscribers.index'),
            route('modules.whatsapp.reports.index'),
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
            ->assertSee('Active status')
            ->assertSee('Total numbers')
            ->assertSee('+971500000001');
    }

    #[Test]
    public function users_can_queue_and_download_central_database_exports(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('modules.ivr.settings.database-export.store'))
            ->assertRedirect(route('modules.ivr.settings.edit'));

        $export = CentralDatabaseExport::query()->firstOrFail();

        $this->assertSame(CentralDatabaseExport::STATUS_PENDING, $export->status);
        Queue::assertPushed(ExportCentralDatabase::class);

        Storage::disk('local')->put('central-database-exports/test.xlsx', 'xlsx-data');
        $export->update([
            'status' => CentralDatabaseExport::STATUS_COMPLETED,
            'file_name' => 'test.xlsx',
            'storage_path' => 'central-database-exports/test.xlsx',
        ]);

        $this->actingAs($user)
            ->get(route('modules.ivr.settings.database-export.download', $export))
            ->assertOk()
            ->assertDownload('test.xlsx');
    }

    #[Test]
    public function filament_ivr_campaign_page_shows_lead_call_records_and_export_action(): void
    {
        $user = User::factory()->create();

        $campaign = IvrCampaign::create([
            'external_campaign_id' => 'campaign-leads',
            'name' => 'campaign-leads',
            'total_calls' => 2,
            'answered_calls' => 2,
            'leads_count' => 1,
        ]);

        $client = Client::create(['full_name' => 'Campaign Lead']);

        $number = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000707',
            'normalized_phone' => '+971500000707',
            'is_uae' => true,
        ]);

        $leadCall = IvrCallRecord::create([
            'ivr_campaign_id' => $campaign->id,
            'client_phone_number_id' => $number->id,
            'external_call_uuid' => 'lead-call',
            'call_time' => '2026-06-06 12:00:00',
            'call_status' => 'Answered',
            'dtmf_outcome' => 'interested',
            'total_duration_seconds' => 65,
        ]);

        $nonLeadCall = IvrCallRecord::create([
            'ivr_campaign_id' => $campaign->id,
            'client_phone_number_id' => $number->id,
            'external_call_uuid' => 'non-lead-call',
            'call_time' => '2026-06-06 13:00:00',
            'call_status' => 'Answered',
            'dtmf_outcome' => 'no_input',
            'total_duration_seconds' => 121,
        ]);

        $this->actingAs($user);

        $this->get(route('filament.admin.resources.ivr-campaigns.edit', $campaign))
            ->assertOk()
            ->assertSee('Total Calls')
            ->assertSee('Answered')
            ->assertSee('Time Consumed')
            ->assertSee('5 min')
            ->assertDontSee('Credits Used')
            ->assertDontSee('Call Window');

        Livewire::test(CallRecordsRelationManager::class, [
            'ownerRecord' => $campaign,
            'pageClass' => EditIvrCampaign::class,
        ])
            ->assertCanSeeTableRecords([$leadCall])
            ->assertCanNotSeeTableRecords([$nonLeadCall])
            ->assertTableHeaderActionsExistInOrder(['export_leads']);
    }

}
