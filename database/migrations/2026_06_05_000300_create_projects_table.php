<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('name');
            $table->integer('dld_project_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('community_id');
            $table->index('dld_project_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
