<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the old communities/regions geography with official_areas + marketing_areas.
 *
 * All client and project rows are cleared — the user will reimport from clean CSVs.
 * client_phone_numbers rows are preserved (IVR/WhatsApp history stays intact).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Detach phone numbers from clients before clearing (nullable FK, safe to nullify)
        DB::statement('UPDATE client_phone_numbers SET client_id = NULL');

        // Clear dependent tables in correct FK order
        DB::table('client_communities')->truncate();
        DB::table('client_interactions')->truncate();
        DB::table('client_sources')->truncate();
        DB::table('client_emails')->truncate();
        DB::table('client_tags')->truncate();
        DB::table('clients')->truncate();
        DB::table('projects')->truncate();

        // ── Drop client_communities (replaced by ownerships) ──────────────────
        Schema::dropIfExists('client_communities');

        // ── Drop old projects community FK, reshape projects table ────────────
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['community_id']);
            $table->dropIndex(['community_id']);
            $table->dropIndex(['active']);
            $table->dropColumn('community_id');
            $table->dropColumn('active');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->string('emirate')->nullable()->after('id');
            $table->foreignId('marketing_area_id')->nullable()->after('emirate')
                ->constrained('marketing_areas')->nullOnDelete();
            $table->foreignId('official_area_id')->nullable()->after('marketing_area_id')
                ->constrained('official_areas')->nullOnDelete();
            $table->string('developer_name')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('dld_project_id');

            $table->index('emirate');
            $table->index('marketing_area_id');
            $table->index('official_area_id');
            $table->index('is_active');
        });

        // ── Restructure clients: drop old geography FKs, add emirate + country_iso ──
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS clients_country_id_idx');
            DB::statement('DROP INDEX IF EXISTS clients_region_id_idx');
            DB::statement('DROP INDEX IF EXISTS clients_community_id_idx');
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropForeign(['community_id']);
            $table->dropForeign(['region_id']);
            $table->dropForeign(['country_id']);
            $table->dropColumn(['community_id', 'region_id', 'country_id']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('country_iso', 2)->nullable()->after('id');
            $table->string('emirate')->nullable()->after('country_iso');

            $table->index('country_iso');
            $table->index('emirate');
        });

        // ── Drop communities and regions (no longer needed) ───────────────────
        Schema::dropIfExists('communities');
        Schema::dropIfExists('regions');
    }

    public function down(): void
    {
        // Irreversible — schema was redesigned; restore from backup if needed
        throw new \RuntimeException('This migration cannot be reversed. Restore from backup.');
    }
};
