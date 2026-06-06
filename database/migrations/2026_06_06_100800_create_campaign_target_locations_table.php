<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_target_locations', function (Blueprint $table): void {
            $table->id();
            // Nullable campaign_id allows this to be used by both IVR and WhatsApp campaigns
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('campaign_type')->nullable()->comment('ivr | whatsapp');
            $table->string('emirate');
            $table->foreignId('marketing_area_id')->nullable()->constrained('marketing_areas')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->boolean('include_projects')->default(true);
            $table->boolean('include_buildings')->default(true);
            $table->timestamps();

            $table->index(['campaign_id', 'campaign_type']);
            $table->index(['emirate', 'marketing_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_target_locations');
    }
};
