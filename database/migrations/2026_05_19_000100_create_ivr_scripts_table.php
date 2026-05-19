<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_scripts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('audio_file_path')->nullable();
            $table->string('audio_original_name')->nullable();
            $table->text('audio_script')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_scripts');
    }
};
