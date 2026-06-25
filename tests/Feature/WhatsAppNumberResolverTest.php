<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppNumberResolver;
use App\Modules\WhatsApp\Support\WhatsAppUnsubscriberImportProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppNumberResolverTest extends TestCase
{
    use RefreshDatabase;

    /** The real-world number from the report: stored without Mexico's dropped "1". */
    private function seedMexicoNumber(): ClientPhoneNumber
    {
        $client = Client::create(['full_name' => 'MX Contact']);

        return ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '524434630828',
            'normalized_phone' => '+524434630828',
            'national_number' => '4434630828',
            'country_code' => '52',
            'detected_country' => 'MX',
            'is_uae' => false,
            'verification_status' => 'verified',
        ]);
    }

    #[Test]
    public function it_matches_a_legacy_format_number_to_the_existing_record(): void
    {
        $existing = $this->seedMexicoNumber();

        // Legacy format with the dropped "1" — libphonenumber now rejects it as invalid.
        $resolved = app(WhatsAppNumberResolver::class)->resolveExisting('5214434630828');

        $this->assertNotNull($resolved);
        $this->assertSame($existing->id, $resolved->id);
    }

    #[Test]
    public function it_returns_null_when_nothing_on_file_matches(): void
    {
        $this->seedMexicoNumber();

        $this->assertNull(app(WhatsAppNumberResolver::class)->resolveExisting('5219999999999'));
    }

    #[Test]
    public function the_unsub_import_suppresses_the_existing_record_not_a_new_phantom(): void
    {
        $existing = $this->seedMexicoNumber();
        $phonesBefore = ClientPhoneNumber::count();

        $path = 'whatsapp/imports/unsubscribers/test-'.uniqid().'.csv';
        $absolute = storage_path('app/private/'.$path);
        @mkdir(dirname($absolute), 0777, true);
        file_put_contents($absolute, "phone,name,reason\n5214434630828,,opted out\n");

        $import = WhatsAppImport::create([
            'type' => 'unsubscribers',
            'status' => 'pending',
            'original_file_name' => 'test.csv',
            'stored_file_name' => 'test.csv',
            'storage_path' => $path,
        ]);

        app(WhatsAppUnsubscriberImportProcessor::class)->process($import);

        @unlink($absolute);

        // Suppressed the existing record, and did NOT create a new phantom phone.
        $this->assertSame($phonesBefore, ClientPhoneNumber::count());
        $this->assertDatabaseHas('contact_suppressions', [
            'client_phone_number_id' => $existing->id,
            'channel' => 'whatsapp',
        ]);
    }
}
