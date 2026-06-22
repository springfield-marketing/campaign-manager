<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CREATE INDEX CONCURRENTLY cannot run inside a transaction.
    public $withinTransaction = false;

    public function up(): void
    {
        // Template Performance: GROUP BY template_name with conditional sums on delivery_status.
        // A covering (template_name, delivery_status) index lets Postgres aggregate without
        // touching the heap for every one of the ~5M message rows.
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS whatsapp_messages_template_perf_idx ON whatsapp_messages (template_name, delivery_status) WHERE template_name IS NOT NULL AND template_name <> ''");

        // Failure Analysis: WHERE delivery_status = 'FAILED' GROUP BY failure_reason with a
        // distinct count of numbers. Partial covering index over just the failed rows.
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS whatsapp_messages_failure_reason_idx ON whatsapp_messages (failure_reason, client_phone_number_id) WHERE delivery_status = 'FAILED'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS whatsapp_messages_template_perf_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS whatsapp_messages_failure_reason_idx');
    }
};
