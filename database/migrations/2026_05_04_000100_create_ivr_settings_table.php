<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('monthly_minutes_quota')->default(50000);
            $table->decimal('price_per_minute_under', 8, 4)->default(0.3700);
            $table->decimal('price_per_minute_over', 8, 4)->default(0.4000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_settings');
    }
};
