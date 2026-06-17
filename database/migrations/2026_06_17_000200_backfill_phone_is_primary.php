<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give every client exactly one primary phone, and stop it ever drifting again.
     *
     * Historically `is_primary` was only set on freshly-imported rows, so the bulk of
     * existing contacts had zero primaries (Client::primaryPhone() returned null and the
     * admin UI rendered a blank number) while ~11k had several (hasOne returned an
     * arbitrary one). We reset the flag and deterministically promote a single best number
     * per client, then a partial unique index makes "at most one primary per client" a
     * hard invariant — the uniqueness guard that an earlier migration had dropped.
     *
     * Preference order: a verified number, then the explicit priority, then UAE, then a
     * WhatsApp-capable line, then most recently imported, then lowest id as a stable tie-break.
     */
    public function up(): void
    {
        DB::statement('UPDATE client_phone_numbers SET is_primary = false WHERE is_primary = true');

        DB::statement(<<<'SQL'
            WITH ranked AS (
                SELECT DISTINCT ON (client_id) id
                FROM client_phone_numbers
                WHERE client_id IS NOT NULL
                ORDER BY
                    client_id,
                    (verification_status = 'verified') DESC,
                    priority ASC,
                    is_uae DESC,
                    is_whatsapp DESC,
                    last_imported_at DESC NULLS LAST,
                    id ASC
            )
            UPDATE client_phone_numbers p
            SET is_primary = true, updated_at = now()
            FROM ranked r
            WHERE p.id = r.id
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX client_phone_numbers_one_primary_per_client
             ON client_phone_numbers (client_id) WHERE is_primary'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS client_phone_numbers_one_primary_per_client');
    }
};
