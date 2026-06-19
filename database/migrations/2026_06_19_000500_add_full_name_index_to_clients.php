<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Contacts list (ClientResource) defaults to ORDER BY full_name. With no index on full_name,
 * Postgres seq-scans + sorts all ~1.1M clients on every page load (~380ms). A btree on full_name
 * turns that into an ordered index scan. Built CONCURRENTLY so it doesn't lock writes on the large
 * table in production.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS clients_full_name_index ON clients (full_name)');

            return;
        }

        Schema::table('clients', fn (Blueprint $table) => $table->index('full_name'));
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS clients_full_name_index');

            return;
        }

        Schema::table('clients', fn (Blueprint $table) => $table->dropIndex(['full_name']));
    }
};
