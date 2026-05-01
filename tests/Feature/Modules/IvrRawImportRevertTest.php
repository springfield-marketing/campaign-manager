<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Models\User;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IvrRawImportRevertTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function raw_import_revert_removes_contacts_created_only_by_that_import(): void
    {
        $user = User::factory()->create();

        $import = IvrImport::create([
            'type' => 'raw_contacts',
            'status' => 'completed',
            'original_file_name' => 'mistake.csv',
            'stored_file_name' => 'mistake.csv',
            'storage_path' => 'ivr/imports/raw/mistake.csv',
        ]);

        $client = Client::create(['full_name' => 'Mistaken Client']);

        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000999',
            'normalized_phone' => '+971500000999',
            'is_uae' => true,
            'usage_status' => 'active',
            'is_primary' => true,
            'priority' => 1,
        ]);

        ClientSource::create([
            'client_id' => $client->id,
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'ivr',
            'source_type' => 'raw_import',
            'source_name' => 'mistake',
            'source_file_name' => $import->original_file_name,
            'source_reference' => (string) $import->id,
        ]);

        $this->actingAs($user)
            ->delete(route('modules.ivr.imports.destroy', $import))
            ->assertRedirect(route('modules.ivr.imports.index'));

        $this->assertDatabaseHas('ivr_imports', [
            'id' => $import->id,
            'status' => 'reverted',
        ]);

        $this->assertDatabaseMissing('client_phone_numbers', ['id' => $phoneNumber->id]);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseMissing('client_sources', ['source_reference' => (string) $import->id]);
    }

    #[Test]
    public function raw_import_revert_keeps_contacts_with_other_sources_or_call_history(): void
    {
        $user = User::factory()->create();

        $import = IvrImport::create([
            'type' => 'raw_contacts',
            'status' => 'completed',
            'original_file_name' => 'shared.csv',
            'stored_file_name' => 'shared.csv',
            'storage_path' => 'ivr/imports/raw/shared.csv',
        ]);

        $client = Client::create(['full_name' => 'Shared Client']);

        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => '0500000888',
            'normalized_phone' => '+971500000888',
            'is_uae' => true,
            'usage_status' => 'active',
            'is_primary' => true,
            'priority' => 1,
            'last_source_name' => 'shared',
        ]);

        ClientSource::create([
            'client_id' => $client->id,
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'ivr',
            'source_type' => 'raw_import',
            'source_name' => 'shared',
            'source_file_name' => $import->original_file_name,
            'source_reference' => (string) $import->id,
        ]);

        ClientSource::create([
            'client_id' => $client->id,
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'ivr',
            'source_type' => 'raw_import',
            'source_name' => 'older-safe-source',
            'source_reference' => 'previous-import',
        ]);

        IvrCallRecord::create([
            'client_phone_number_id' => $phoneNumber->id,
            'external_call_uuid' => 'call-shared-1',
        ]);

        $this->actingAs($user)
            ->delete(route('modules.ivr.imports.destroy', $import))
            ->assertRedirect(route('modules.ivr.imports.index'));

        $this->assertDatabaseHas('ivr_imports', [
            'id' => $import->id,
            'status' => 'reverted',
        ]);

        $this->assertDatabaseHas('client_phone_numbers', [
            'id' => $phoneNumber->id,
            'last_source_name' => 'older-safe-source',
        ]);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('client_sources', ['source_reference' => 'previous-import']);
        $this->assertDatabaseMissing('client_sources', ['source_reference' => (string) $import->id]);
    }
}
