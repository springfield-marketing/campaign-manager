<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Default-sort indexes for two heavily-used Filament tables that sort large tables by an
 * unindexed timestamp, forcing a full seq-scan + sort on every page load:
 *   - IVR/WhatsApp Numbers  → client_phone_numbers ORDER BY created_at   (~1.07M rows, ~370ms)
 *   - IVR/WhatsApp Unsubs    → contact_suppressions ORDER BY suppressed_at (~772k rows, ~150ms)
 * A plain btree serves DESC sorts too (Postgres scans it backward). Built CONCURRENTLY (prod-safe).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS client_phone_numbers_created_at_index ON client_phone_numbers (created_at)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS contact_suppressions_suppressed_at_index ON contact_suppressions (suppressed_at)');

            return;
        }

        Schema::table('client_phone_numbers', fn (Blueprint $table) => $table->index('created_at'));
        Schema::table('contact_suppressions', fn (Blueprint $table) => $table->index('suppressed_at'));
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_phone_numbers_created_at_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS contact_suppressions_suppressed_at_index');

            return;
        }

        Schema::table('client_phone_numbers', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('contact_suppressions', fn (Blueprint $table) => $table->dropIndex(['suppressed_at']));
    }
};
