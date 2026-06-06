<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('emirate');
            $table->unsignedInteger('source_area_id')->nullable()->comment('DLD area ID or equivalent government ID');
            $table->string('area_name_en');
            $table->unsignedSmallInteger('zone_id')->nullable()->comment('1=Non-Freehold 2=Freehold');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['emirate', 'area_name_en']);
            $table->index('emirate');
            $table->index('source_area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_areas');
    }
};
