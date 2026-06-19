<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Substring search (Filament `->searchable()` / the phone filter) issues `LIKE '%term%'` /
 * `ILIKE '%term%'` with a LEADING wildcard, which a btree can't serve — so it full-scans:
 *   - Contacts search:     clients.full_name (~1.1M)            ~95ms / query (worst case)
 *   - Numbers phone search: client_phone_numbers.normalized_phone / raw_phone (~1.07M) ~36ms
 * GIN trigram indexes (pg_trgm) make these substring searches index-backed (sub-ms). Built
 * CONCURRENTLY (prod-safe). Postgres-only; the test suite runs on Postgres.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS clients_full_name_trgm_index ON clients USING gin (full_name gin_trgm_ops)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS client_phone_numbers_normalized_phone_trgm_index ON client_phone_numbers USING gin (normalized_phone gin_trgm_ops)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS client_phone_numbers_raw_phone_trgm_index ON client_phone_numbers USING gin (raw_phone gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS clients_full_name_trgm_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_phone_numbers_normalized_phone_trgm_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS client_phone_numbers_raw_phone_trgm_index');
        // Leave the pg_trgm extension in place — other indexes/queries may rely on it.
    }
};
