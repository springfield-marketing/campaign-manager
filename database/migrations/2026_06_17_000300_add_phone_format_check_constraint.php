<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Replace the old placeholder-only guard with a validated format constraint that keeps
     * the corruption we just cleaned out from ever re-entering:
     *
     *   - normalized_phone must be an optional '+' followed by 9–15 digits and nothing else.
     *     This enforces the length window AND rejects Excel scientific-notation strings
     *     ("9.71E+11") and any stray letters/punctuation in one shot.
     *   - the trailing local number must not look like a placeholder (long zero runs,
     *     a single repeated digit, or a straight ascending/descending run).
     *
     * Rows that have been explicitly classified (verification_status <> 'unverified', i.e.
     * proven real = verified, or proven fake = invalid) are exempt: once a number's status
     * is known from real campaign outcomes, the structural heuristic no longer applies.
     *
     * Added as NOT VALID then immediately VALIDATEd so it both guards new writes and is
     * proven against existing data (the verification backfill cleared all prior violators).
     */
    public function up(): void
    {
        // Postgres-only: uses regex operators, regexp_replace and VALIDATE CONSTRAINT.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE client_phone_numbers
            ADD CONSTRAINT client_phone_numbers_format_check
            CHECK (
                verification_status <> 'unverified'
                OR (
                    normalized_phone ~ '^\+?[0-9]{9,15}$'
                    AND right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~ '0{6,}$'
                    AND right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~ '(.)\1{5,}'
                    AND right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~ '(0123456|1234567|2345678|3456789|9876543|8765432|7654321|6543210|01234567|12345678|23456789|98765432|87654321|76543210|012345678|123456789|987654321|876543210)$'
                )
            ) NOT VALID
        SQL);

        DB::statement('ALTER TABLE client_phone_numbers VALIDATE CONSTRAINT client_phone_numbers_format_check');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE client_phone_numbers DROP CONSTRAINT IF EXISTS client_phone_numbers_format_check');
    }
};
