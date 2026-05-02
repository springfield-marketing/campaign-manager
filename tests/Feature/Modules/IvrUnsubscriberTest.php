<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'usage_status' => 'active',
            'is_primary' => true,
            'priority' => 1,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'unsubscribers.csv',
            "phone,name\n0500000200,Existing Lead\n0500000201,New Unsubscriber\n",
        );

        $this->actingAs($user)
            ->post(route('modules.ivr.unsubscribers.store'), ['file' => $file])
            ->assertRedirect(route('modules.ivr.unsubscribers.index'));

        $this->assertDatabaseHas('contact_suppressions', [
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'ivr',
            'reason' => 'unsubscribe',
            'released_at' => null,
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
            'usage_status' => 'active',
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

        $this->actingAs($user)
            ->get(route('modules.ivr.unsubscribers.index', ['phone' => '500000300', 'name' => 'Remove']))
            ->assertOk()
            ->assertSee('+971500000300')
            ->assertSee('Remove Me');

        $this->actingAs($user)
            ->delete(route('modules.ivr.unsubscribers.destroy', $suppression))
            ->assertRedirect();

        $this->assertNotNull($suppression->fresh()->released_at);
        $this->assertNull($phoneNumber->fresh()->unsubscribed_at);
    }

    #[Test]
    public function campaign_unsubscribers_are_managed_on_the_unsubscriber_page(): void
    {
        $user = User::factory()->create();
        $client = Client::create(['full_name' => 'Campaign Opt Out']);
        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000400',
            'normalized_phone' => '+971500000400',
            'is_uae' => true,
            'usage_status' => 'active',
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

        $this->actingAs($user)
            ->get(route('modules.ivr.unsubscribers.index', ['phone' => '500000400']))
            ->assertOk()
            ->assertSee('+971500000400')
            ->assertSee('Campaign: 42');

        $this->actingAs($user)
            ->delete(route('modules.ivr.unsubscribers.destroy', $suppression))
            ->assertRedirect();

        $this->assertNotNull($suppression->fresh()->released_at);
        $this->assertNull($phoneNumber->fresh()->unsubscribed_at);
    }
}
