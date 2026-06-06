<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS ivr_call_records_campaign_status_idx
                ON ivr_call_records (ivr_campaign_id, call_status)');

            DB::statement('CREATE INDEX IF NOT EXISTS ivr_call_records_campaign_dtmf_idx
                ON ivr_call_records (ivr_campaign_id, dtmf_outcome)');

            DB::statement('CREATE INDEX IF NOT EXISTS whatsapp_messages_campaign_delivery_idx
                ON whatsapp_messages (whatsapp_campaign_id, delivery_status)');

            return;
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ivr_call_records_campaign_status_idx
            ON ivr_call_records (ivr_campaign_id, call_status)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ivr_call_records_campaign_dtmf_idx
            ON ivr_call_records (ivr_campaign_id, dtmf_outcome)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS whatsapp_messages_campaign_delivery_idx
            ON whatsapp_messages (whatsapp_campaign_id, delivery_status)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS ivr_call_records_campaign_status_idx');
            DB::statement('DROP INDEX IF EXISTS ivr_call_records_campaign_dtmf_idx');
            DB::statement('DROP INDEX IF EXISTS whatsapp_messages_campaign_delivery_idx');

            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ivr_call_records_campaign_status_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ivr_call_records_campaign_dtmf_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS whatsapp_messages_campaign_delivery_idx');
    }
};
