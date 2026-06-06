<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_staging', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id')->index();
            // Raw contact fields — allowed to be messy
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('country_iso', 2)->nullable();
            $table->string('emirate')->nullable();
            // Raw location strings — will be resolved to FKs during processing
            $table->string('raw_official_area')->nullable();
            $table->string('raw_marketing_area')->nullable();
            $table->string('raw_project_name')->nullable();
            $table->string('raw_building_name')->nullable();
            $table->string('raw_unit_reference')->nullable();
            // Resolved FK IDs (filled by processor)
            $table->foreignId('official_area_id')->nullable()->constrained('official_areas')->nullOnDelete();
            $table->foreignId('marketing_area_id')->nullable()->constrained('marketing_areas')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            // Ownership metadata
            $table->string('relationship_type')->nullable();
            $table->string('confidence_level')->nullable();
            $table->string('source')->nullable();
            // Processing state: pending | matched | needs_review | rejected
            $table->string('status')->default('pending')->index();
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staging');
    }
};
