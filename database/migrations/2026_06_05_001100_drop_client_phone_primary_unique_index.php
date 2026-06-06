<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_phone_numbers_one_primary_per_client');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS client_phone_numbers_one_primary_per_client');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS client_phone_numbers_one_primary_per_client ON client_phone_numbers (client_id) WHERE is_primary AND client_id IS NOT NULL');

            return;
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS client_phone_numbers_one_primary_per_client ON client_phone_numbers (client_id) WHERE is_primary AND client_id IS NOT NULL');
    }
};
