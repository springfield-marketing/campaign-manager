<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('original_source')->nullable()->after('completeness_score');
        });

        // Backfill: prefer earliest non-campaign source; fall back to earliest campaign source.
        DB::statement("
            UPDATE clients
            SET original_source = (
                SELECT COALESCE(
                    (
                        SELECT source_name
                        FROM client_sources
                        WHERE client_id = clients.id
                          AND source_type <> 'campaign_result'
                        ORDER BY created_at ASC
                        LIMIT 1
                    ),
                    (
                        SELECT source_name
                        FROM client_sources
                        WHERE client_id = clients.id
                        ORDER BY created_at ASC
                        LIMIT 1
                    )
                )
            )
            WHERE EXISTS (
                SELECT 1 FROM client_sources WHERE client_id = clients.id
            )
        ");
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('original_source');
        });
    }
};
