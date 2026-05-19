<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ivr_campaigns', function (Blueprint $table): void {
            $table->foreignId('ivr_script_id')->nullable()->after('id')->constrained('ivr_scripts')->nullOnDelete();
        });

        Schema::table('ivr_imports', function (Blueprint $table): void {
            $table->foreignId('ivr_script_id')->nullable()->after('audio_script')->constrained('ivr_scripts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ivr_imports', function (Blueprint $table): void {
            $table->dropForeign(['ivr_script_id']);
            $table->dropColumn('ivr_script_id');
        });

        Schema::table('ivr_campaigns', function (Blueprint $table): void {
            $table->dropForeign(['ivr_script_id']);
            $table->dropColumn('ivr_script_id');
        });
    }
};
