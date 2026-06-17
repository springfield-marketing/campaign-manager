<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Mirrors App\Modules\IVR\Support\PhoneNormalizer::looksLikePlaceholder() as a database-level
     * safety net, so a placeholder/garbage number can't slip in through a path that bypasses the
     * normalizer (raw SQL, console scripts, other modules).
     *
     * Added NOT VALID and never validated against existing rows on purpose: ~1,984 legacy rows
     * (mostly old incomplete numbers zero-padded by a prior import) already violate this rule but
     * caused no real damage (no merged leads). Cleaning that backlog is a separate decision — this
     * constraint only blocks new bad data going forward.
     */
    private const LOCAL_NUMBER_CHECK = <<<'SQL'
        right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~ '0{6,}$'
        AND right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~ '(.)\1{5,}'
        AND right(regexp_replace(normalized_phone, '\D', '', 'g'), 9) !~
            '(0123456|1234567|2345678|3456789|9876543|8765432|7654321|6543210|01234567|12345678|23456789|98765432|87654321|76543210|012345678|123456789|987654321|876543210)$'
        SQL;

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "ALTER TABLE client_phone_numbers ADD CONSTRAINT client_phone_numbers_not_placeholder_check ".
            "CHECK (normalized_phone IS NULL OR (".self::LOCAL_NUMBER_CHECK.")) NOT VALID"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE client_phone_numbers DROP CONSTRAINT IF EXISTS client_phone_numbers_not_placeholder_check');
    }
};
