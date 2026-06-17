<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed verification_status from real campaign history so callable-number quality is
     * self-evident, then re-base the placeholder guard.
     *
     * A number is "verified" when it provably reached a person: an IVR call was Answered,
     * or a WhatsApp message reached the device (DELIVERED/READ/REPLIED/STOPPED). It is
     * "invalid" only when it both looks like a placeholder AND never showed any sign of
     * life — WhatsApp "FAILED" alone is NOT used, since those failures are overwhelmingly
     * sender-side (Meta quality throttling, credit, experiments), not bad numbers.
     *
     * The legacy `client_phone_numbers_not_placeholder_check` is dropped first: it is a
     * NOT VALID constraint, but NOT VALID still re-checks any row you UPDATE, which would
     * block us from marking a placeholder-looking-but-genuinely-reached number as verified.
     * A stricter, validated constraint is re-added in a later migration.
     */
    public function up(): void
    {
        // Postgres-only: uses regex operators / regexp_replace and ALTER TABLE DROP CONSTRAINT.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE client_phone_numbers DROP CONSTRAINT IF EXISTS client_phone_numbers_not_placeholder_check');

        DB::statement(<<<'SQL'
            UPDATE client_phone_numbers p
            SET verification_status = 'verified', updated_at = now()
            WHERE verification_status <> 'verified'
              AND (
                EXISTS (SELECT 1 FROM ivr_call_records r
                        WHERE r.client_phone_number_id = p.id AND r.call_status = 'Answered')
                OR EXISTS (SELECT 1 FROM whatsapp_messages w
                        WHERE w.client_phone_number_id = p.id
                          AND w.delivery_status IN ('DELIVERED','READ','REPLIED','STOPPED'))
              )
        SQL);

        DB::statement(<<<'SQL'
            UPDATE client_phone_numbers
            SET verification_status = 'invalid', updated_at = now()
            WHERE verification_status = 'unverified'
              AND (
                right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) ~ '0{6,}$'
                OR right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) ~ '(.)\1{5,}'
              )
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Re-add the legacy guard (NOT VALID) so the schema matches its prior shape.
        // The verification_status data backfill itself is not reverted.
        DB::statement(<<<'SQL'
            ALTER TABLE client_phone_numbers
            ADD CONSTRAINT client_phone_numbers_not_placeholder_check
            CHECK (
                normalized_phone IS NULL
                OR (
                    right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~ '0{6,}$'
                    AND right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~ '(.)\1{5,}'
                    AND right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~ '(0123456|1234567|2345678|3456789|9876543|8765432|7654321|6543210|01234567|12345678|23456789|98765432|87654321|76543210|012345678|123456789|987654321|876543210)$'
                )
            ) NOT VALID
        SQL);
    }
};
