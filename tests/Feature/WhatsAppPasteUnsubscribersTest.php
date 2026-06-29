<?php

namespace Tests\Feature;

use App\Filament\Resources\WhatsAppImports\Pages\ListWhatsAppImports;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppPasteUnsubscribersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pasting_numbers_suppresses_existing_and_creates_unmatched(): void
    {
        $this->actingAs(User::factory()->create());

        // An existing number — should be matched and suppressed, not duplicated.
        $client = Client::create(['full_name' => 'Existing Contact']);
        $existing = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '+971501234501',
            'normalized_phone' => '+971501234501',
            'national_number' => '501234501',
            'country_code' => '971',
            'detected_country' => 'AE',
            'is_uae' => true,
            'verification_status' => 'verified',
        ]);

        $phonesBefore = ClientPhoneNumber::count();

        Livewire::test(ListWhatsAppImports::class)
            ->callTableAction('paste_unsubscribers', data: [
                // existing + brand-new + a duplicate of the existing (tests dedup)
                'numbers' => "+971501234501\n+971553948271\n+971501234501",
                'platform' => null,
                'reason' => 'Bulk opt-out',
            ]);

        $import = WhatsAppImport::firstOrFail();

        // History row created and processed inline.
        $this->assertSame('unsubscribers', $import->type);
        $this->assertSame('paste', $import->summary['source'] ?? null);
        $this->assertSame(2, (int) $import->processed_rows);   // 3 pasted, deduped to 2
        $this->assertSame(2, (int) $import->successful_rows);
        $this->assertSame(0, (int) $import->failed_rows);

        // Existing number suppressed (matched, not duplicated).
        $this->assertDatabaseHas('contact_suppressions', [
            'client_phone_number_id' => $existing->id,
            'channel' => 'whatsapp',
        ]);

        // Unmatched number created and suppressed (exactly one new phone row).
        $this->assertSame($phonesBefore + 1, ClientPhoneNumber::count());
        $new = ClientPhoneNumber::where('normalized_phone', '+971553948271')->firstOrFail();
        $this->assertDatabaseHas('contact_suppressions', [
            'client_phone_number_id' => $new->id,
            'channel' => 'whatsapp',
        ]);

        @unlink(storage_path('app/private/'.$import->storage_path));
    }

    #[Test]
    public function it_rejects_an_empty_paste(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListWhatsAppImports::class)
            ->callTableAction('paste_unsubscribers', data: [
                'numbers' => "  \n , ; \n ",
                'platform' => null,
                'reason' => null,
            ]);

        $this->assertSame(0, WhatsAppImport::count());
        $this->assertSame(0, ContactSuppression::count());
    }
}
