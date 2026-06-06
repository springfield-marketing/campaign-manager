<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_review_queue', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id')->index();
            $table->foreignId('staging_id')->constrained('import_staging')->cascadeOnDelete();
            // Snapshot of raw data at time of review (staging row may be cleared)
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('emirate')->nullable();
            $table->string('raw_official_area')->nullable();
            $table->string('raw_marketing_area')->nullable();
            $table->string('raw_project_name')->nullable();
            $table->string('raw_building_name')->nullable();
            $table->string('raw_unit_reference')->nullable();
            $table->string('relationship_type')->nullable();
            $table->string('confidence_level')->nullable();
            $table->string('source')->nullable();
            // Best-guess IDs the processor found (may be null)
            $table->foreignId('suggested_official_area_id')->nullable()->constrained('official_areas')->nullOnDelete();
            $table->foreignId('suggested_marketing_area_id')->nullable()->constrained('marketing_areas')->nullOnDelete();
            $table->foreignId('suggested_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('suggested_building_id')->nullable()->constrained('buildings')->nullOnDelete();
            // Why it couldn't auto-match
            $table->string('issue_reason');
            // Resolution: pending | approved | rejected
            $table->string('resolution')->default('pending')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'resolution']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_review_queue');
    }
};
