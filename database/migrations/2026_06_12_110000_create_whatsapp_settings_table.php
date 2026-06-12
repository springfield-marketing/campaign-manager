<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('lock_key')->unique();

            // Number health — dead detection
            $table->unsignedSmallInteger('hard_fail_threshold')->default(3);
            $table->unsignedSmallInteger('bulk_dead_threshold')->default(10);

            // Engagement — no-click cooldown
            $table->unsignedSmallInteger('no_engagement_threshold')->default(5);
            $table->unsignedSmallInteger('cooldown_no_engagement_days')->default(90);

            // Minimum gap between any sends (0 = disabled)
            $table->unsignedSmallInteger('min_days_between_sends')->default(0);

            // Failure-based cooldowns
            $table->unsignedSmallInteger('cooldown_quality_hold_days')->default(3);
            $table->unsignedSmallInteger('cooldown_experiment_days')->default(7);
            $table->unsignedSmallInteger('cooldown_regional_days')->default(30);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};
