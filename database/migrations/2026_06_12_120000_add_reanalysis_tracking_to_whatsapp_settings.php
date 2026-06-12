<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->string('reanalysis_status', 20)->nullable()->after('cooldown_regional_days');
            $table->timestamp('reanalysis_started_at')->nullable()->after('reanalysis_status');
            $table->timestamp('reanalysis_completed_at')->nullable()->after('reanalysis_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table): void {
            $table->dropColumn(['reanalysis_status', 'reanalysis_started_at', 'reanalysis_completed_at']);
        });
    }
};
