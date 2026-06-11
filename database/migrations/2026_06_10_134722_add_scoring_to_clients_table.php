<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            // standard | premium | high_net_worth | vip
            // Null = not yet classified. Manual tier is never overwritten by auto-scoring.
            $table->string('tier', 20)->nullable()->default(null)->after('interest');

            // 0–100 auto-computed from ownership data (property count, premium areas, relationship types).
            $table->smallInteger('wealth_score')->nullable()->default(null)->after('tier');

            // 0–100 auto-computed from which contact fields are populated.
            $table->smallInteger('completeness_score')->nullable()->default(null)->after('wealth_score');

            $table->index('tier');
            $table->index('wealth_score');
            $table->index('completeness_score');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropIndex(['tier']);
            $table->dropIndex(['wealth_score']);
            $table->dropIndex(['completeness_score']);
            $table->dropColumn(['tier', 'wealth_score', 'completeness_score']);
        });
    }
};
