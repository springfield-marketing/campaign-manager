<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ivr_imports', function (Blueprint $table): void {
            $table->foreignId('tag_id')
                ->nullable()
                ->after('ivr_script_id')
                ->constrained('tags')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ivr_imports', function (Blueprint $table): void {
            $table->dropForeign(['tag_id']);
            $table->dropColumn('tag_id');
        });
    }
};
