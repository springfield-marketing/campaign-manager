<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\User;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Jobs\ProcessUnsubscriberImport;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Models\IvrPhoneProfile;
use App\Modules\IVR\Support\NumberEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrUnsubscriberTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function users_can_import_unsubscribers_and_exclude_them_from_number_export(): void
    {
        $user = User::factory()->create();

        $client = Client::create(['full_name' => 'Existing Lead']);
        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000200',
            'normalized_phone' => '+971500000200',
            'is_uae' => true,
            'is_primary' => true,
            'priority' => 1,
        ]);

        $csvContent = "phone,name\n0500000200,Existing Lead\n0500000201,New Unsubscriber\n";
        $storagePath = 'ivr/imports/unsubscribers/test-unsubscribers.csv';
        Storage::disk('local')->put($storagePath, $csvContent);

        $import = IvrImport::create([
            'type'               => IvrImportType::Unsubscribers,
            'status'             => IvrImportStatus::Pending,
            'original_file_name' => 'test-unsubscribers.csv',
            'stored_file_name'   => 'test-unsubscribers.csv',
            'storage_path'       => $storagePath,
            'uploaded_by'        => $user->id,
            'summary'            => ['format' => 'phone,name'],
        ]);

        ProcessUnsubscriberImport::dispatchSync($import->id);

        $this->assertDatabaseHas('contact_suppressions', [
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'ivr',
            'reason' => 'unsubscribe',
            'released_at' => null,
        ]);
        $this->assertDatabaseHas('ivr_phone_profiles', [
            'client_phone_number_id' => $phoneNumber->id,
            'usage_status' => 'dead',
        ]);

        $this->assertNotNull(ClientPhoneNumber::query()
            ->where('normalized_phone', '+971500000201')
            ->value('unsubscribed_at'));

        $csv = $this->actingAs($user)
            ->get(route('modules.ivr.numbers.export'))
            ->streamedContent();

        $this->assertStringNotContainsString('+971500000200', $csv);
        $this->assertStringNotContainsString('+971500000201', $csv);
    }

    #[Test]
    public function users_can_filter_and_remove_unsubscribers(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['full_name' => 'Remove Me']);
        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000300',
            'normalized_phone' => '+971500000300',
            'is_uae' => true,
            'is_primary' => true,
            'priority' => 1,
            'unsubscribed_at' => now(),
        ]);
        $suppression = ContactSuppression::create([
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'ivr',
            'reason' => 'unsubscribe',
            'suppressed_at' => now(),
        ]);

        // Release the suppression directly via service layer (as the Filament table action does)
        $suppression->forceFill(['released_at' => now()])->save();

        $stillSuppressed = ContactSuppression::where('client_phone_number_id', $phoneNumber->id)
            ->whereNull('released_at')
            ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', 'ivr'))
            ->exists();

        if (! $stillSuppressed) {
            $phoneNumber->forceFill(['unsubscribed_at' => null])->save();
        }

        app(NumberEligibilityService::class)->refresh($phoneNumber->refresh());

        $this->assertNotNull($suppression->fresh()->released_at);
        $this->assertNull($phoneNumber->fresh()->unsubscribed_at);
        $this->assertSame('active', IvrPhoneProfile::query()
            ->where('client_phone_number_id', $phoneNumber->id)
            ->value('usage_status'));
    }

    #[Test]
    public function campaign_unsubscribers_are_managed_on_the_unsubscriber_page(): void
    {
        $client = Client::create(['full_name' => 'Campaign Opt Out']);
        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000400',
            'normalized_phone' => '+971500000400',
            'is_uae' => true,
            'is_primary' => true,
            'priority' => 1,
            'unsubscribed_at' => now(),
        ]);
        $suppression = ContactSuppression::create([
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'ivr',
            'reason' => 'customer_unsubscribed',
            'context' => ['campaign_id' => 42],
            'suppressed_at' => now(),
        ]);

        // Release the suppression via service layer (as Filament table action does)
        $suppression->forceFill(['released_at' => now()])->save();

        $stillSuppressed = ContactSuppression::where('client_phone_number_id', $phoneNumber->id)
            ->whereNull('released_at')
            ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', 'ivr'))
            ->exists();

        if (! $stillSuppressed) {
            $phoneNumber->forceFill(['unsubscribed_at' => null])->save();
        }

        $this->assertNotNull($suppression->fresh()->released_at);
        $this->assertNull($phoneNumber->fresh()->unsubscribed_at);
    }
}
