<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_imports', function (Blueprint $table): void {
            $table->jsonb('column_mapping')->nullable()->after('storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_imports', function (Blueprint $table): void {
            $table->dropColumn('column_mapping');
        });
    }
};
