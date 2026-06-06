<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->string('emirate');
            $table->string('name');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('marketing_area_id')->nullable()->constrained('marketing_areas')->nullOnDelete();
            $table->foreignId('official_area_id')->nullable()->constrained('official_areas')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('emirate');
            $table->index('project_id');
            $table->index('marketing_area_id');
            $table->index('official_area_id');
            // A building name is unique within a project
            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
