<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reviewer can manually push a quarantined number to "dead". Because reanalysis recomputes
 * usage_status on every run, that manual decision needs to persist somewhere the recompute can
 * honour — this flag. When set, the updater forces usage_status = 'dead' regardless of the
 * automatic rules, and never clears it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_phone_profiles', function (Blueprint $table): void {
            $table->boolean('manually_dead')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_phone_profiles', function (Blueprint $table): void {
            $table->dropColumn('manually_dead');
        });
    }
};
