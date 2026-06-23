<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\WhatsAppExportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PruneWhatsAppExportBatchesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_batches_past_the_window_and_keeps_recent_ones(): void
    {
        $client = Client::create(['full_name' => 'Test Contact']);

        // verification_status=verified uses the documented escape hatch on the phone format CHECK.
        $p1 = ClientPhoneNumber::create([
            'client_id' => $client->id, 'raw_phone' => '0500000001',
            'normalized_phone' => '+971500000001', 'verification_status' => 'verified',
        ]);
        $p2 = ClientPhoneNumber::create([
            'client_id' => $client->id, 'raw_phone' => '0500000002',
            'normalized_phone' => '+971500000002', 'verification_status' => 'verified',
        ]);

        $old = WhatsAppExportBatch::create(['name' => 'Old batch', 'record_count' => 2]);
        $old->phoneNumbers()->attach([$p1->id, $p2->id]);
        // Backdate beyond the 7-day window (created_at isn't fillable, so set it directly).
        DB::table('whatsapp_export_batches')->where('id', $old->id)
            ->update(['created_at' => now()->subDays(8)]);

        $recent = WhatsAppExportBatch::create(['name' => 'Recent batch', 'record_count' => 1]);
        $recent->phoneNumbers()->attach([$p1->id]);

        $this->artisan('whatsapp:prune-export-batches')->assertSuccessful();

        // Old batch and its pivot rows are gone (the pivot via ON DELETE CASCADE).
        $this->assertDatabaseMissing('whatsapp_export_batches', ['id' => $old->id]);
        $this->assertDatabaseMissing('whatsapp_export_batch_numbers', ['whatsapp_export_batch_id' => $old->id]);

        // Recent batch and its pivot rows survive.
        $this->assertDatabaseHas('whatsapp_export_batches', ['id' => $recent->id]);
        $this->assertDatabaseHas('whatsapp_export_batch_numbers', ['whatsapp_export_batch_id' => $recent->id]);
    }

    #[Test]
    public function the_days_option_overrides_the_window(): void
    {
        $batch = WhatsAppExportBatch::create(['name' => 'Two days old', 'record_count' => 0]);
        DB::table('whatsapp_export_batches')->where('id', $batch->id)
            ->update(['created_at' => now()->subDays(2)]);

        // Default window (7d) keeps it; a 1-day window prunes it.
        $this->artisan('whatsapp:prune-export-batches')->assertSuccessful();
        $this->assertDatabaseHas('whatsapp_export_batches', ['id' => $batch->id]);

        $this->artisan('whatsapp:prune-export-batches', ['--days' => 1])->assertSuccessful();
        $this->assertDatabaseMissing('whatsapp_export_batches', ['id' => $batch->id]);
    }
}
