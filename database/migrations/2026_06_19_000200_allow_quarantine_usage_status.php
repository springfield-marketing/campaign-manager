<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow the new 'quarantine' value in whatsapp_phone_profiles.usage_status (strong dead
 * candidates parked for manual review). Postgres-only: the check constraint is a pgsql object,
 * and the test SQLite path never has it — guard so the suite doesn't choke.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE whatsapp_phone_profiles DROP CONSTRAINT IF EXISTS whatsapp_phone_profiles_usage_status_check');
        DB::statement("ALTER TABLE whatsapp_phone_profiles ADD CONSTRAINT whatsapp_phone_profiles_usage_status_check CHECK (usage_status IN ('active', 'cooldown', 'quarantine', 'dead'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE whatsapp_phone_profiles DROP CONSTRAINT IF EXISTS whatsapp_phone_profiles_usage_status_check');
        DB::statement("ALTER TABLE whatsapp_phone_profiles ADD CONSTRAINT whatsapp_phone_profiles_usage_status_check CHECK (usage_status IN ('active', 'cooldown', 'dead'))");
    }
};
