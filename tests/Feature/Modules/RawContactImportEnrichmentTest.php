<?php

namespace Tests\Feature\Modules;

use App\Models\Client;
use App\Models\MarketingArea;
use App\Models\Project;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\RawImportProcessor;
use App\Support\RawContactImportEnricher;
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
        $marketingArea = MarketingArea::create([
            'emirate' => 'Dubai',
            'name' => 'Dubai Marina',
            'is_active' => true,
        ]);

        $project = Project::create([
            'emirate' => 'Dubai',
            'marketing_area_id' => $marketingArea->id,
            'name' => 'Marina Gate',
            'dld_project_id' => 98765,
            'is_active' => true,
        ]);

        Storage::disk('local')->put($path, implode("\n", [
            'name,phone,email,country_iso,city,community,project_name,relationship_type,confidence_level,source',
            'Aisha Rahman,+971506601155,aisha@example.com,AE,Dubai,Dubai Marina,Marina Gate,buyer_interest,high,June Leads',
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

        $this->assertDatabaseHas('client_emails', [
            'email' => 'aisha@example.com',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('ownerships', [
            'emirate' => 'Dubai',
            'marketing_area_id' => $marketingArea->id,
            'project_id' => $project->id,
            'relationship_type' => 'buyer_interest',
            'confidence_level' => 'high',
            'source' => 'June Leads',
        ]);

        $this->assertDatabaseHas('client_sources', [
            'source_name' => 'June Leads',
            'source_file_name' => 'rich-contacts.csv',
        ]);
    }

    #[Test]
    public function raw_contact_import_counts_repeated_phones_inside_the_same_file_as_duplicates(): void
    {
        $path = 'ivr/imports/raw/repeated-phone.csv';

        Storage::disk('local')->put($path, implode("\n", [
            'name,phone,email,country_iso,emirate,relationship_type,confidence_level,source',
            'First Owner,+971500023490,first@example.com,AE,Dubai,owner,high,Repeated Source',
            'Second Owner,+971500023490,second@example.com,AE,Dubai,owner,high,Repeated Source',
        ]));

        $import = IvrImport::create([
            'type' => 'raw_contacts',
            'status' => 'pending',
            'original_file_name' => 'repeated-phone.csv',
            'stored_file_name' => 'repeated-phone.csv',
            'storage_path' => $path,
        ]);

        app(RawImportProcessor::class)->process($import);

        $this->assertDatabaseHas('ivr_imports', [
            'id' => $import->id,
            'status' => 'completed',
            'successful_rows' => 2,
            'duplicate_rows' => 1,
        ]);

        $this->assertDatabaseCount('client_phone_numbers', 1);
        $this->assertDatabaseCount('client_sources', 2);

        $this->assertDatabaseHas('client_phone_numbers', [
            'normalized_phone' => '+971500023490',
        ]);
    }

    /**
     * IMP-001 — regression for the client 496904 incident: 8 unrelated Iranian numbers
     * collapsed onto one "✅ Instagram Dm |" client because resolveClient matched on the
     * (name, emirate, country) tuple and the placeholder name made distinct leads collide.
     * See docs/data-rules/imports.md.
     */
    #[Test]
    public function imp_001_stub_named_rows_never_merge_distinct_leads(): void
    {
        $enricher = app(RawContactImportEnricher::class);

        // Two unrelated leads, both arriving with the same placeholder name and no location,
        // each with a brand-new phone (no existing ClientPhoneNumber to match on).
        $a = $enricher->resolveClient(['name' => '✅ Instagram Dm |', 'normalized_phone' => '+989121260734']);
        $b = $enricher->resolveClient(['name' => '✅ Instagram Dm |', 'normalized_phone' => '+989125209034']);

        $this->assertNotSame($a->id, $b->id, 'Stub-named leads must not be merged onto one client');
        $this->assertSame(2, Client::where('full_name', '✅ Instagram Dm |')->count());
    }

    /**
     * IMP-002 — phone is the identity anchor, a name is not. A real personal name arriving on a
     * brand-new phone must NOT merge onto an existing same-name client: two people share a name,
     * and silently merging them is how "super clients" formed. We bias to under-merge (cheap to
     * reconcile) over over-merge (destructive). Supersedes the old IMP-001 guard rail that merged
     * real names on the (name, emirate, country) tuple. See docs/data-rules/imports.md.
     */
    #[Test]
    public function imp_002_real_named_rows_with_different_phones_do_not_merge(): void
    {
        $enricher = app(RawContactImportEnricher::class);

        $a = $enricher->resolveClient(['name' => 'Aisha Rahman', 'emirate' => 'Dubai', 'country_iso' => 'AE', 'normalized_phone' => '+971500000001']);
        $b = $enricher->resolveClient(['name' => 'Aisha Rahman', 'emirate' => 'Dubai', 'country_iso' => 'AE', 'normalized_phone' => '+971500000002']);

        $this->assertNotSame($a->id, $b->id, 'Different phones with the same name must not be merged');
        $this->assertSame(2, Client::where('full_name', 'Aisha Rahman')->count());
    }

    /**
     * IMP-002 corollary: re-importing the SAME number still resolves to the same client (the
     * phone match happens before the name branch), so the no-merge rule does not duplicate
     * legitimate re-imports.
     */
    #[Test]
    public function imp_002_same_phone_still_resolves_to_one_client(): void
    {
        $enricher = app(RawContactImportEnricher::class);

        $first  = $enricher->resolveClient(['name' => 'Bilal Khan', 'emirate' => 'Dubai', 'country_iso' => 'AE', 'normalized_phone' => '+971527948051']);
        \App\Models\ClientPhoneNumber::create([
            'client_id' => $first->id, 'raw_phone' => '+971527948051', 'normalized_phone' => '+971527948051',
            'is_uae' => true, 'is_primary' => true, 'priority' => 1,
        ]);
        $second = $enricher->resolveClient(['name' => 'Bilal Khan', 'emirate' => 'Dubai', 'country_iso' => 'AE', 'normalized_phone' => '+971527948051']);

        $this->assertSame($first->id, $second->id, 'Same phone must resolve to the same client');
    }

    /**
     * Institution names (bank/developer/agency) are real shared entities: they keep one record
     * per identity tuple so their own lines don't fragment (spec §5), unlike personal names.
     */
    #[Test]
    public function institution_names_keep_a_single_record_per_identity_tuple(): void
    {
        $enricher = app(RawContactImportEnricher::class);

        $a = $enricher->resolveClient(['name' => 'Emirates Islamic Bank', 'emirate' => 'Dubai', 'country_iso' => 'AE', 'normalized_phone' => '+971500000010']);
        $b = $enricher->resolveClient(['name' => 'Emirates Islamic Bank', 'emirate' => 'Dubai', 'country_iso' => 'AE', 'normalized_phone' => '+971500000011']);

        $this->assertSame($a->id, $b->id, 'Institution names should resolve to a single shared record');
        $this->assertSame(1, Client::where('full_name', 'Emirates Islamic Bank')->count());
    }
}
