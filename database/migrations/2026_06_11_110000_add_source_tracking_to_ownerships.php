<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ownerships', function (Blueprint $table) {
            $table->renameColumn('source', 'last_source_name');
            $table->jsonb('source_names')->nullable()->after('confidence_level');
            $table->timestamp('first_confirmed_at')->nullable()->after('source_names');
        });

        // Backfill: seed source_names from existing last_source_name and
        // first_confirmed_at from created_at for all existing rows.
        DB::statement("
            UPDATE ownerships
            SET
                source_names       = CASE
                                         WHEN last_source_name IS NOT NULL
                                         THEN jsonb_build_array(last_source_name)
                                         ELSE '[]'::jsonb
                                     END,
                first_confirmed_at = created_at
            WHERE source_names IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('ownerships', function (Blueprint $table) {
            $table->dropColumn(['source_names', 'first_confirmed_at']);
            $table->renameColumn('last_source_name', 'source');
        });
    }
};
