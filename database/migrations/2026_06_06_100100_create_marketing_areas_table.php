<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('emirate');
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['emirate', 'name']);
            $table->index('emirate');
        });

        // M:M join — one marketing area can span multiple official areas (e.g. JLT spans two DLD zones)
        Schema::create('marketing_area_official_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_area_id')->constrained('marketing_areas')->cascadeOnDelete();
            $table->foreignId('official_area_id')->constrained('official_areas')->cascadeOnDelete();
            $table->string('confidence_level')->nullable()->comment('high|medium|low');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['marketing_area_id', 'official_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_area_official_areas');
        Schema::dropIfExists('marketing_areas');
    }
};
