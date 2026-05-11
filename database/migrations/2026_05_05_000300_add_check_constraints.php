<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE ivr_imports ADD CONSTRAINT ivr_imports_type_check
            CHECK (type IN ('raw_contacts', 'campaign_results', 'unsubscribers'))");

        DB::statement("ALTER TABLE ivr_imports ADD CONSTRAINT ivr_imports_status_check
            CHECK (status IN ('pending', 'processing', 'completed', 'completed_with_errors',
                              'failed', 'deleting', 'deleted', 'delete_failed',
                              'reverting', 'reverted', 'revert_failed'))");

        DB::statement("ALTER TABLE ivr_call_records ADD CONSTRAINT ivr_call_records_dtmf_outcome_check
            CHECK (dtmf_outcome IS NULL OR dtmf_outcome IN ('interested', 'more_info', 'unsubscribe', 'no_input', 'other'))");

        DB::statement("ALTER TABLE client_phone_numbers ADD CONSTRAINT client_phone_numbers_usage_status_check
            CHECK (usage_status IN ('active', 'inactive', 'dead'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE client_phone_numbers DROP CONSTRAINT client_phone_numbers_usage_status_check');
        DB::statement('ALTER TABLE ivr_call_records DROP CONSTRAINT ivr_call_records_dtmf_outcome_check');
        DB::statement('ALTER TABLE ivr_imports DROP CONSTRAINT ivr_imports_status_check');
        DB::statement('ALTER TABLE ivr_imports DROP CONSTRAINT ivr_imports_type_check');
    }
};
