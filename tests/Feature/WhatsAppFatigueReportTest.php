<?php

namespace Tests\Feature;

use App\Filament\Pages\WhatsAppInsightsPage;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppReport;
use App\Modules\WhatsApp\Support\WhatsAppFatigueReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppFatigueReportTest extends TestCase
{
    use RefreshDatabase;

    private int $importId;

    private int $campaignSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importId = DB::table('whatsapp_imports')->insertGetId([
            'type' => 'campaign_results',
            'status' => 'completed',
            'original_file_name' => 'seed.csv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function it_generates_a_fatigue_csv_with_correct_scores(): void
    {
        // A: 1 campaign, replied → low fatigue (~6, Low). B: 4 campaigns, no reply → higher (67, High).
        $this->seedNumber('+971500000001', repliedCampaigns: 1, sentOnlyCampaigns: 0);
        $this->seedNumber('+971500000002', repliedCampaigns: 0, sentOnlyCampaigns: 4);

        $report = WhatsAppReport::create([
            'type' => WhatsAppReport::TYPE_FATIGUE,
            'status' => WhatsAppReport::STATUS_PENDING,
            'requested_by' => User::factory()->create()->id,
        ]);

        app(WhatsAppFatigueReportGenerator::class)->generate($report);

        $report->refresh();
        $this->assertSame(WhatsAppReport::STATUS_COMPLETED, $report->status);
        $this->assertSame(2, (int) $report->processed_rows);
        $this->assertTrue(Storage::disk('local')->exists($report->storage_path));

        $rows = $this->parseCsv($report->storage_path);

        $this->assertSame('1', $rows['+971500000001']['campaigns_60d']);
        $this->assertSame('1', $rows['+971500000001']['reply_rate']);
        $this->assertSame('Low', $rows['+971500000001']['fatigue_band']);

        $this->assertSame('4', $rows['+971500000002']['campaigns_60d']);
        $this->assertSame('0', $rows['+971500000002']['reply_rate']);
        $this->assertSame('67', $rows['+971500000002']['fatigue_score']);
        $this->assertSame('High', $rows['+971500000002']['fatigue_band']);
    }

    #[Test]
    public function the_insights_page_renders(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(WhatsAppInsightsPage::class)->assertOk();
    }

    private function seedNumber(string $phone, int $repliedCampaigns, int $sentOnlyCampaigns): void
    {
        $client = Client::create(['full_name' => 'N'.$phone, 'emirate' => 'Dubai', 'tier' => 'standard']);
        $cpn = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => $phone,
            'normalized_phone' => $phone,
            'verification_status' => 'verified',
        ]);

        $make = function (string $status) use ($cpn): void {
            $campaignId = DB::table('whatsapp_campaigns')->insertGetId([
                'name' => 'C'.(++$this->campaignSeq),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('whatsapp_messages')->insert([
                'whatsapp_campaign_id' => $campaignId,
                'whatsapp_import_id' => $this->importId,
                'client_phone_number_id' => $cpn->id,
                'delivery_status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        for ($i = 0; $i < $repliedCampaigns; $i++) {
            $make('REPLIED');
        }
        for ($i = 0; $i < $sentOnlyCampaigns; $i++) {
            $make('SENT');
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function parseCsv(string $path): array
    {
        $lines = array_filter(explode("\n", trim(Storage::disk('local')->get($path))));
        $header = str_getcsv(array_shift($lines));

        $out = [];
        foreach ($lines as $line) {
            $row = array_combine($header, str_getcsv($line));
            $out[$row['phone']] = $row;
        }

        return $out;
    }
}
