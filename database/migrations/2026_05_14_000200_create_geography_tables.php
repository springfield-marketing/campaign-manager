<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('iso_code', 2)->unique();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['country_id', 'name']);
        });

        Schema::create('communities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_community_id')->nullable()->constrained('communities')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['region_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communities');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('countries');
    }
};
