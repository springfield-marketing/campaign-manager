<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_reports', function (Blueprint $table): void {
            // Start of the analysis window. Null = all-time (lifetime).
            $table->timestamp('window_from')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_reports', function (Blueprint $table): void {
            $table->dropColumn('window_from');
        });
    }
};
