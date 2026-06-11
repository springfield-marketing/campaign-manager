<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_monthly_summaries', function (Blueprint $table): void {
            $table->unsignedInteger('unsubscribed_count')->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_monthly_summaries', function (Blueprint $table): void {
            $table->dropColumn('unsubscribed_count');
        });
    }
};
