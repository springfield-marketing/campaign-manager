<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrPhoneProfile;
use App\Modules\IVR\Support\NumberEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrNumberEligibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function historical_use_count_does_not_make_an_ivr_number_inactive(): void
    {
        $phoneNumber = $this->phoneNumber('+971500001001');

        foreach (range(1, 4) as $index) {
            IvrCallRecord::create([
                'client_phone_number_id' => $phoneNumber->id,
                'external_call_uuid' => "answered-{$index}",
                'call_status' => 'Answered',
                'call_time' => now()->subDays(30 + $index),
            ]);
        }

        app(NumberEligibilityService::class)->refresh($phoneNumber);

        $this->assertSame('active', IvrPhoneProfile::query()
            ->where('client_phone_number_id', $phoneNumber->id)
            ->value('usage_status'));
    }

    #[Test]
    public function five_consecutive_no_answers_make_an_ivr_number_inactive(): void
    {
        $phoneNumber = $this->phoneNumber('+971500001002');

        foreach (range(1, 5) as $index) {
            IvrCallRecord::create([
                'client_phone_number_id' => $phoneNumber->id,
                'external_call_uuid' => "missed-{$index}",
                'call_status' => 'Missed',
                'call_time' => now()->subDays(30 + $index),
            ]);
        }

        app(NumberEligibilityService::class)->refresh($phoneNumber);

        $this->assertSame('inactive', IvrPhoneProfile::query()
            ->where('client_phone_number_id', $phoneNumber->id)
            ->value('usage_status'));
    }

    private function phoneNumber(string $normalizedPhone): ClientPhoneNumber
    {
        $client = Client::create(['full_name' => 'Eligibility Test']);

        return ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => $normalizedPhone,
            'normalized_phone' => $normalizedPhone,
            'is_uae' => true,
            'is_primary' => true,
            'priority' => 1,
        ]);
    }
}
