<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\User;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrCampaignLeadExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function campaign_lead_export_includes_pressed_one_and_pressed_two_outcomes(): void
    {
        $user = User::factory()->create();
        $campaign = IvrCampaign::create(['external_campaign_id' => 'campaign-001']);

        $client = Client::create(['full_name' => 'Lead Client']);
        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000001',
            'normalized_phone' => '+971500000001',
            'is_uae' => true,
            'usage_status' => 'active',
        ]);

        IvrCallRecord::create([
            'ivr_campaign_id' => $campaign->id,
            'client_phone_number_id' => $phoneNumber->id,
            'external_call_uuid' => 'pressed-one',
            'dtmf_outcome' => 'interested',
            'call_time' => now()->subMinutes(2),
        ]);

        IvrCallRecord::create([
            'ivr_campaign_id' => $campaign->id,
            'client_phone_number_id' => $phoneNumber->id,
            'external_call_uuid' => 'pressed-two',
            'dtmf_outcome' => 'more_info',
            'call_time' => now()->subMinute(),
        ]);

        IvrCallRecord::create([
            'ivr_campaign_id' => $campaign->id,
            'client_phone_number_id' => $phoneNumber->id,
            'external_call_uuid' => 'not-a-lead',
            'dtmf_outcome' => 'unsubscribe',
            'call_time' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('modules.ivr.results.leads.export', $campaign));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('interested', $csv);
        $this->assertStringContainsString('more_info', $csv);
        $this->assertStringNotContainsString('unsubscribe', $csv);
    }
}
