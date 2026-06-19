<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-derive channel-membership flags from real campaign activity, now that they're owned by the
 * campaign-results importers rather than raw import:
 *   - is_whatsapp = true for every number that has a WhatsApp message (was lagging badly — only
 *     newly-created numbers got flagged before, so ~222k were set while ~545k had activity).
 *   - is_ivr      = true for every number that has an IVR call record.
 *
 * Postgres-only (UPDATE ... uses an IN subquery; the test SQLite path has no real data to backfill).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            UPDATE client_phone_numbers SET is_whatsapp = true
            WHERE is_whatsapp = false
              AND id IN (SELECT DISTINCT client_phone_number_id FROM whatsapp_messages WHERE client_phone_number_id IS NOT NULL)
        ');

        DB::statement('
            UPDATE client_phone_numbers SET is_ivr = true
            WHERE is_ivr = false
              AND id IN (SELECT DISTINCT client_phone_number_id FROM ivr_call_records WHERE client_phone_number_id IS NOT NULL)
        ');
    }

    public function down(): void
    {
        // A backfill from activity cannot be reliably reversed (we can't tell which flags were
        // pre-existing), and the flags are derivable again by re-running. No-op.
    }
};
