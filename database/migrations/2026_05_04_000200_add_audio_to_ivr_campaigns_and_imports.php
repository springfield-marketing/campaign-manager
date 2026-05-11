<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ivr_imports', function (Blueprint $table): void {
            $table->string('audio_file_path')->nullable()->after('storage_path');
            $table->string('audio_original_name')->nullable()->after('audio_file_path');
            $table->text('audio_script')->nullable()->after('audio_original_name');
        });

        Schema::table('ivr_campaigns', function (Blueprint $table): void {
            $table->string('audio_file_path')->nullable()->after('summary');
            $table->string('audio_original_name')->nullable()->after('audio_file_path');
            $table->text('audio_script')->nullable()->after('audio_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('ivr_imports', function (Blueprint $table): void {
            $table->dropColumn(['audio_file_path', 'audio_original_name', 'audio_script']);
        });

        Schema::table('ivr_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['audio_file_path', 'audio_original_name', 'audio_script']);
        });
    }
};
