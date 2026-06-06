<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS whatsapp_messages_phone_scheduled_idx ON whatsapp_messages (client_phone_number_id, scheduled_at DESC, id DESC) WHERE client_phone_number_id IS NOT NULL');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ivr_call_records_import_idx ON ivr_call_records (ivr_import_id) WHERE ivr_import_id IS NOT NULL');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ivr_import_errors_import_idx ON ivr_import_errors (ivr_import_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS whatsapp_import_errors_import_idx ON whatsapp_import_errors (whatsapp_import_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS clients_country_id_idx ON clients (country_id) WHERE country_id IS NOT NULL');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS clients_region_id_idx ON clients (region_id) WHERE region_id IS NOT NULL');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS clients_community_id_idx ON clients (community_id) WHERE community_id IS NOT NULL');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS client_tags_tag_id_idx ON client_tags (tag_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS client_communities_project_id_idx ON client_communities (project_id) WHERE project_id IS NOT NULL');

            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS whatsapp_messages_phone_scheduled_idx ON whatsapp_messages (client_phone_number_id, scheduled_at DESC, id DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS ivr_call_records_import_idx ON ivr_call_records (ivr_import_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS ivr_import_errors_import_idx ON ivr_import_errors (ivr_import_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS whatsapp_import_errors_import_idx ON whatsapp_import_errors (whatsapp_import_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS clients_country_id_idx ON clients (country_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS clients_region_id_idx ON clients (region_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS clients_community_id_idx ON clients (community_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS client_tags_tag_id_idx ON client_tags (tag_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS client_communities_project_id_idx ON client_communities (project_id)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_communities_project_id_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_tags_tag_id_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS clients_community_id_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS clients_region_id_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS clients_country_id_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS whatsapp_import_errors_import_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ivr_import_errors_import_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ivr_call_records_import_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS whatsapp_messages_phone_scheduled_idx');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS client_communities_project_id_idx');
        DB::statement('DROP INDEX IF EXISTS client_tags_tag_id_idx');
        DB::statement('DROP INDEX IF EXISTS clients_community_id_idx');
        DB::statement('DROP INDEX IF EXISTS clients_region_id_idx');
        DB::statement('DROP INDEX IF EXISTS clients_country_id_idx');
        DB::statement('DROP INDEX IF EXISTS whatsapp_import_errors_import_idx');
        DB::statement('DROP INDEX IF EXISTS ivr_import_errors_import_idx');
        DB::statement('DROP INDEX IF EXISTS ivr_call_records_import_idx');
        DB::statement('DROP INDEX IF EXISTS whatsapp_messages_phone_scheduled_idx');
    }
};
