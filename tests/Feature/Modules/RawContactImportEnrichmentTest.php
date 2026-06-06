<?php

namespace Tests\Feature\Modules;

use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\RawImportProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RawContactImportEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function raw_contact_import_links_contacts_to_projects_and_communities(): void
    {
        $path = 'ivr/imports/raw/rich-contacts.csv';

        Storage::disk('local')->put($path, implode("\n", [
            'name,phone,email,country_iso,city,community,project_name,dld_project_id,relationship_type,confidence_level,notes,source',
            'Aisha Rahman,+971506601155,aisha@example.com,AE,Dubai,Dubai Marina,Marina Gate,98765,buyer_interest,high,Looking for waterfront unit,June Leads',
        ]));

        $import = IvrImport::create([
            'type' => 'raw_contacts',
            'status' => 'pending',
            'original_file_name' => 'rich-contacts.csv',
            'stored_file_name' => 'rich-contacts.csv',
            'storage_path' => $path,
        ]);

        app(RawImportProcessor::class)->process($import);

        $this->assertDatabaseHas('ivr_imports', [
            'id' => $import->id,
            'status' => 'completed',
            'successful_rows' => 1,
        ]);

        $this->assertDatabaseHas('countries', [
            'iso_code' => 'AE',
        ]);

        $this->assertDatabaseHas('regions', [
            'name' => 'Dubai',
        ]);

        $this->assertDatabaseHas('communities', [
            'name' => 'Dubai Marina',
        ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'Marina Gate',
            'dld_project_id' => 98765,
        ]);

        $this->assertDatabaseHas('client_emails', [
            'email' => 'aisha@example.com',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('client_communities', [
            'relationship_type' => 'buyer_interest',
            'confidence_level' => 'high',
            'source' => 'June Leads',
            'notes' => 'Looking for waterfront unit',
        ]);
    }
}
