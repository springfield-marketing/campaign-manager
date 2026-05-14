<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ivr_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('cooldown_answered_days')->default(14)->after('price_per_minute_over');
            $table->unsignedSmallInteger('cooldown_missed_days')->default(1)->after('cooldown_answered_days');
        });
    }

    public function down(): void
    {
        Schema::table('ivr_settings', function (Blueprint $table): void {
            $table->dropColumn(['cooldown_answered_days', 'cooldown_missed_days']);
        });
    }
};
