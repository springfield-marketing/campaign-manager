<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ivr_settings', function (Blueprint $table): void {
            $table->string('lock_key')->default('default')->after('id');
        });

        DB::table('ivr_settings')->update(['lock_key' => 'default']);

        Schema::table('ivr_settings', function (Blueprint $table): void {
            $table->unique('lock_key');
        });
    }

    public function down(): void
    {
        Schema::table('ivr_settings', function (Blueprint $table): void {
            $table->dropUnique(['lock_key']);
            $table->dropColumn('lock_key');
        });
    }
};
