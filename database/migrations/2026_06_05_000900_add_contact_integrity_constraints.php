<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS client_emails_client_lower_email_unique ON client_emails (client_id, lower(email))');
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS client_emails_one_primary_per_client ON client_emails (client_id) WHERE is_primary');
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS client_communities_unique_project_relationship ON client_communities (client_id, community_id, project_id, relationship_type) WHERE project_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS client_communities_unique_community_relationship ON client_communities (client_id, community_id, relationship_type) WHERE project_id IS NULL');

            DB::statement("ALTER TABLE client_phone_numbers ADD CONSTRAINT client_phone_numbers_verification_status_check CHECK (verification_status IN ('unverified', 'verified', 'invalid')) NOT VALID");
            DB::statement("ALTER TABLE whatsapp_imports ADD CONSTRAINT whatsapp_imports_type_check CHECK (type IN ('raw_contacts', 'campaign_results', 'unsubscribers')) NOT VALID");
            DB::statement("ALTER TABLE whatsapp_imports ADD CONSTRAINT whatsapp_imports_status_check CHECK (status IN ('draft', 'pending', 'processing', 'completed', 'completed_with_errors', 'failed', 'deleting', 'deleted', 'delete_failed', 'reverting', 'reverted', 'revert_failed')) NOT VALID");
            DB::statement("ALTER TABLE whatsapp_messages ADD CONSTRAINT whatsapp_messages_delivery_status_check CHECK (delivery_status IS NULL OR delivery_status IN ('SENT', 'DELIVERED', 'READ', 'REPLIED', 'FAILED', 'STOPPED')) NOT VALID");
            DB::statement("ALTER TABLE whatsapp_phone_profiles ADD CONSTRAINT whatsapp_phone_profiles_usage_status_check CHECK (usage_status IN ('active', 'cooldown', 'dead')) NOT VALID");
            DB::statement("ALTER TABLE client_communities ADD CONSTRAINT client_communities_relationship_type_check CHECK (relationship_type IN ('owner', 'resident', 'tenant', 'buyer_interest', 'seller_interest', 'investor', 'past_owner', 'unknown')) NOT VALID");
            DB::statement("ALTER TABLE client_communities ADD CONSTRAINT client_communities_confidence_level_check CHECK (confidence_level IS NULL OR confidence_level IN ('high', 'medium', 'low')) NOT VALID");
            DB::statement("ALTER TABLE client_interactions ADD CONSTRAINT client_interactions_type_check CHECK (type IN ('ivr_campaign', 'whatsapp_campaign', 'agent_upload', 'manual_entry', 'import', 'note', 'phone_call')) NOT VALID");

            DB::statement('ALTER TABLE client_phone_numbers VALIDATE CONSTRAINT client_phone_numbers_verification_status_check');
            DB::statement('ALTER TABLE whatsapp_imports VALIDATE CONSTRAINT whatsapp_imports_type_check');
            DB::statement('ALTER TABLE whatsapp_imports VALIDATE CONSTRAINT whatsapp_imports_status_check');
            DB::statement('ALTER TABLE whatsapp_messages VALIDATE CONSTRAINT whatsapp_messages_delivery_status_check');
            DB::statement('ALTER TABLE whatsapp_phone_profiles VALIDATE CONSTRAINT whatsapp_phone_profiles_usage_status_check');
            DB::statement('ALTER TABLE client_communities VALIDATE CONSTRAINT client_communities_relationship_type_check');
            DB::statement('ALTER TABLE client_communities VALIDATE CONSTRAINT client_communities_confidence_level_check');
            DB::statement('ALTER TABLE client_interactions VALIDATE CONSTRAINT client_interactions_type_check');

            return;
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS client_emails_client_lower_email_unique ON client_emails (client_id, lower(email))');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS client_emails_one_primary_per_client ON client_emails (client_id) WHERE is_primary');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS client_communities_unique_project_relationship ON client_communities (client_id, community_id, project_id, relationship_type) WHERE project_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS client_communities_unique_community_relationship ON client_communities (client_id, community_id, relationship_type) WHERE project_id IS NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE client_interactions DROP CONSTRAINT IF EXISTS client_interactions_type_check');
            DB::statement('ALTER TABLE client_communities DROP CONSTRAINT IF EXISTS client_communities_confidence_level_check');
            DB::statement('ALTER TABLE client_communities DROP CONSTRAINT IF EXISTS client_communities_relationship_type_check');
            DB::statement('ALTER TABLE whatsapp_phone_profiles DROP CONSTRAINT IF EXISTS whatsapp_phone_profiles_usage_status_check');
            DB::statement('ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS whatsapp_messages_delivery_status_check');
            DB::statement('ALTER TABLE whatsapp_imports DROP CONSTRAINT IF EXISTS whatsapp_imports_status_check');
            DB::statement('ALTER TABLE whatsapp_imports DROP CONSTRAINT IF EXISTS whatsapp_imports_type_check');
            DB::statement('ALTER TABLE client_phone_numbers DROP CONSTRAINT IF EXISTS client_phone_numbers_verification_status_check');

            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_communities_unique_community_relationship');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_communities_unique_project_relationship');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_emails_one_primary_per_client');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_emails_client_lower_email_unique');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS client_communities_unique_community_relationship');
        DB::statement('DROP INDEX IF EXISTS client_communities_unique_project_relationship');
        DB::statement('DROP INDEX IF EXISTS client_emails_one_primary_per_client');
        DB::statement('DROP INDEX IF EXISTS client_emails_client_lower_email_unique');
    }
};
