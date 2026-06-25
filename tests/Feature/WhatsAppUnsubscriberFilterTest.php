<?php

namespace Tests\Feature;

use App\Filament\Resources\WhatsAppUnsubscribers\Pages\ListWhatsAppUnsubscribers;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppUnsubscriberFilterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_phone_filter_finds_a_suppression_searched_by_its_legacy_format(): void
    {
        $this->actingAs(User::factory()->create());

        $client = Client::create(['full_name' => 'MX Contact']);
        $number = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '524434630828',
            'normalized_phone' => '+524434630828',
            'national_number' => '4434630828',
            'country_code' => '52',
            'detected_country' => 'MX',
            'is_uae' => false,
            'verification_status' => 'verified',
        ]);
        $suppression = ContactSuppression::create([
            'client_phone_number_id' => $number->id,
            'channel' => 'whatsapp',
            'reason' => 'manual',
            'suppressed_at' => now(),
        ]);

        // Search with the legacy "1" format — must still surface the suppression on +524434630828.
        Livewire::test(ListWhatsAppUnsubscribers::class)
            ->filterTable('phone', ['phone' => '5214434630828'])
            ->assertCanSeeTableRecords([$suppression]);
    }
}
