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

class IvrCampaignResultsExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function campaign_results_export_includes_records_between_dates(): void
    {
        $user = User::factory()->create();
        $campaign = IvrCampaign::create(['external_campaign_id' => 'campaign-2026']);

        $client = Client::create(['full_name' => 'Export Client', 'email' => 'export@example.com']);
        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000001',
            'normalized_phone' => '+971500000001',
            'is_uae' => true,
        ]);

        IvrCallRecord::create([
            'ivr_campaign_id' => $campaign->id,
            'client_phone_number_id' => $phoneNumber->id,
            'external_call_uuid' => 'inside-range',
            'call_time' => '2026-05-06 12:00:00',
            'call_status' => 'Answered',
            'dtmf_outcome' => 'interested',
            'total_duration_seconds' => 65,
            'talk_time_seconds' => 30,
            'dtmf_extensions' => ['1'],
        ]);

        IvrCallRecord::create([
            'ivr_campaign_id' => $campaign->id,
            'client_phone_number_id' => $phoneNumber->id,
            'external_call_uuid' => 'outside-range',
            'call_time' => '2026-05-08 12:00:00',
            'dtmf_outcome' => 'unsubscribe',
        ]);

        $response = $this->actingAs($user)
            ->get(route('modules.ivr.results.export', [
                'from' => '2026-05-06',
                'to' => '2026-05-06',
            ]));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('inside-range', $csv);
        $this->assertStringContainsString('Export Client', $csv);
        $this->assertStringContainsString('interested', $csv);
        $this->assertStringNotContainsString('outside-range', $csv);
    }
}
