<?php

namespace Tests\Feature\Modules;

use App\Filament\Resources\WhatsAppNumbers\Pages\ListWhatsAppNumbers;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\Tag;
use App\Models\User;
use App\Models\WhatsAppExportBatch;
use App\Modules\WhatsApp\Models\WhatsAppPhoneProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppNumberExportTest extends TestCase
{
    use RefreshDatabase;

    private function activeUaeNumber(Client $client, string $normalized): ClientPhoneNumber
    {
        $number = ClientPhoneNumber::create([
            'client_id'           => $client->id,
            'raw_phone'           => $normalized,
            'normalized_phone'    => $normalized,
            'national_number'     => substr($normalized, 4), // strip "+971" -> "5xxxxxxxx"
            'is_uae'              => true,
            'is_whatsapp'         => true,
            'verification_status' => 'verified',
        ]);

        WhatsAppPhoneProfile::create([
            'client_phone_number_id' => $number->id,
            'usage_status'           => 'active',
        ]);

        return $number;
    }

    /**
     * EXP-001 — regression: the "Export Filtered CSV" action used to re-implement a subset of
     * the table filters by hand and silently dropped the tags (and uae_only/is_lead/suppressed)
     * filter, so a tag-filtered export pulled from a far larger pool than the table showed.
     * The export now reuses getFilteredTableQuery(). See docs/data-rules/exports.md.
     */
    #[Test]
    public function imp_exp_001_export_respects_the_tag_filter(): void
    {
        $user = User::factory()->create();
        $tag  = Tag::create(['name' => 'Paul Database']);

        // Tagged + active -> should be exported.
        $tagged = Client::create(['full_name' => 'Tagged Client']);
        $tagged->tags()->attach($tag->id);
        $taggedNumber = $this->activeUaeNumber($tagged, '+971501230001');

        // Untagged + active -> must NOT be exported (the old code wrongly included it).
        $untagged = Client::create(['full_name' => 'Untagged Client']);
        $untaggedNumber = $this->activeUaeNumber($untagged, '+971501230002');

        Livewire::actingAs($user)
            ->test(ListWhatsAppNumbers::class)
            ->set('tableFilters.wa_status.value', 'active')
            ->set('tableFilters.tags.values', [$tag->id])
            ->callAction('export', data: ['batch_name' => 'EXP-001 tag test']);

        $batch = WhatsAppExportBatch::latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame(1, $batch->record_count, 'Only the tagged number should be exported');

        $exportedIds = DB::table('whatsapp_export_batch_numbers')
            ->where('whatsapp_export_batch_id', $batch->id)
            ->pluck('client_phone_number_id');

        $this->assertTrue($exportedIds->contains($taggedNumber->id));
        $this->assertFalse($exportedIds->contains($untaggedNumber->id));
    }

    /**
     * EXP-001 compliance guard: an unsubscribed (suppressed) number must never be exported,
     * even when it matches the active + tag filters.
     */
    #[Test]
    public function imp_exp_001_export_never_includes_suppressed_numbers(): void
    {
        $user = User::factory()->create();
        $tag  = Tag::create(['name' => 'Paul Database']);

        $tagged = Client::create(['full_name' => 'Tagged Client']);
        $tagged->tags()->attach($tag->id);
        $keep = $this->activeUaeNumber($tagged, '+971501230001');

        $suppressedClient = Client::create(['full_name' => 'Suppressed Tagged Client']);
        $suppressedClient->tags()->attach($tag->id);
        $suppressed = $this->activeUaeNumber($suppressedClient, '+971501230002');
        ContactSuppression::create([
            'client_phone_number_id' => $suppressed->id,
            'channel'                => 'whatsapp',
            'reason'                 => 'unsubscribe',
            'suppressed_at'          => now(),
        ]);

        Livewire::actingAs($user)
            ->test(ListWhatsAppNumbers::class)
            ->set('tableFilters.wa_status.value', 'active')
            ->set('tableFilters.tags.values', [$tag->id])
            ->callAction('export', data: ['batch_name' => 'EXP-001 suppression test']);

        $batch = WhatsAppExportBatch::latest('id')->first();

        $exportedIds = DB::table('whatsapp_export_batch_numbers')
            ->where('whatsapp_export_batch_id', $batch->id)
            ->pluck('client_phone_number_id');

        $this->assertTrue($exportedIds->contains($keep->id));
        $this->assertFalse($exportedIds->contains($suppressed->id), 'Suppressed numbers must never be exported');
    }
}
