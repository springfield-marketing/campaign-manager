<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrNumberExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function export_uses_one_best_eligible_number_per_client(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['full_name' => 'Aisha Client', 'city' => 'Dubai']);

        ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000001',
            'normalized_phone' => '+971500000001',
            'is_uae' => true,
            'usage_status' => 'active',
            'is_primary' => false,
            'priority' => 1,
            'last_called_at' => now()->subDays(10),
        ]);

        $bestNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000002',
            'normalized_phone' => '+971500000002',
            'is_uae' => true,
            'usage_status' => 'active',
            'is_primary' => true,
            'priority' => 10,
            'last_called_at' => now()->subDay(),
        ]);

        ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000003',
            'normalized_phone' => '+971500000003',
            'is_uae' => true,
            'usage_status' => 'active',
            'is_primary' => true,
            'priority' => 1,
            'cooldown_until' => now()->addDay(),
        ]);

        $otherClient = Client::create(['full_name' => 'Suppressed Client']);
        $suppressedNumber = ClientPhoneNumber::create([
            'client_id' => $otherClient->id,
            'raw_phone' => '0500000004',
            'normalized_phone' => '+971500000004',
            'is_uae' => true,
            'usage_status' => 'active',
            'is_primary' => true,
            'priority' => 1,
        ]);

        ContactSuppression::create([
            'client_phone_number_id' => $suppressedNumber->id,
            'channel' => 'ivr',
            'reason' => 'unsubscribe',
            'suppressed_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('modules.ivr.numbers.export'));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString($bestNumber->normalized_phone, $csv);
        $this->assertStringContainsString('Aisha Client', $csv);
        $this->assertStringNotContainsString('+971500000001', $csv);
        $this->assertStringNotContainsString('+971500000003', $csv);
        $this->assertStringNotContainsString($suppressedNumber->normalized_phone, $csv);
    }
}
