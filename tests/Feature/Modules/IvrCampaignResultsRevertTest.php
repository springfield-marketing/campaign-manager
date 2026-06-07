<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Models\User;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrCampaign;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\CampaignResultsReverter;
use App\Modules\IVR\Support\NumberEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrCampaignResultsRevertTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function campaign_results_revert_removes_call_records_campaign_sources_and_orphan_phone(): void
    {
        $user = User::factory()->create();

        $import = IvrImport::create([
            'type' => 'campaign_results',
            'status' => 'completed',
            'original_file_name' => 'campaign-101.csv',
            'stored_file_name' => 'campaign-101.csv',
            'storage_path' => 'ivr/imports/results/campaign-101.csv',
            'summary' => ['order_number' => 'campaign-101'],
        ]);

        $campaign = IvrCampaign::create([
            'external_campaign_id' => 'campaign-101',
            'name' => 'campaign-101',
        ]);

        $client = Client::create(['full_name' => 'Campaign Lead']);

        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000101',
            'normalized_phone' => '+971500000101',
            'is_uae' => true,
            'is_primary' => true,
            'priority' => 1,
        ]);

        IvrCallRecord::create([
            'ivr_campaign_id' => $campaign->id,
            'ivr_import_id' => $import->id,
            'client_phone_number_id' => $phoneNumber->id,
            'external_call_uuid' => 'campaign-call-101',
            'call_status' => 'Answered',
            'dtmf_outcome' => 'interested',
        ]);

        ClientSource::create([
            'client_id' => $client->id,
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'ivr',
            'source_type' => 'campaign_result',
            'source_file_name' => $import->original_file_name,
            'source_reference' => 'campaign-101',
        ]);

        app(CampaignResultsReverter::class)->revert(
            import: $import,
            userId: $user->id,
            reason: null,
            eligibilityService: app(NumberEligibilityService::class),
        );

        $this->assertDatabaseHas('ivr_imports', [
            'id' => $import->id,
            'status' => 'reverted',
            'reverted_by' => $user->id,
        ]);

        $this->assertDatabaseMissing('ivr_call_records', ['external_call_uuid' => 'campaign-call-101']);
        $this->assertDatabaseMissing('ivr_campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('client_sources', ['source_reference' => 'campaign-101']);
        $this->assertDatabaseMissing('client_phone_numbers', ['id' => $phoneNumber->id]);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }
}
