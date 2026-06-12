<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_imports', function (Blueprint $table): void {
            $table->boolean('lenient_phones')->default(false)->after('column_mapping');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_imports', function (Blueprint $table): void {
            $table->dropColumn('lenient_phones');
        });
    }
};
