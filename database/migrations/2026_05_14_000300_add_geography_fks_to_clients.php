<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            // Nullable FKs added alongside the existing text columns.
            // The old columns (country, city, community) are intentionally kept
            // until Phase 3 is complete and verified.
            $table->foreignId('country_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->foreignId('community_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['region_id']);
            $table->dropForeign(['community_id']);
            $table->dropColumn(['country_id', 'region_id', 'community_id']);
        });
    }
};
