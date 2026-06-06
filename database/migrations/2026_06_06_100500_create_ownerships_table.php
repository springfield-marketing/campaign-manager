<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownerships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('emirate');
            $table->foreignId('official_area_id')->nullable()->constrained('official_areas')->nullOnDelete();
            $table->foreignId('marketing_area_id')->nullable()->constrained('marketing_areas')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->string('unit_reference')->nullable();
            $table->string('relationship_type');
            $table->string('confidence_level')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            // Fast lookup for campaign targeting
            $table->index(['emirate', 'marketing_area_id', 'relationship_type']);
            $table->index(['marketing_area_id', 'relationship_type']);
            $table->index(['project_id', 'relationship_type']);
            $table->index(['building_id', 'relationship_type']);
            $table->index('client_id');
            $table->index('official_area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownerships');
    }
};
