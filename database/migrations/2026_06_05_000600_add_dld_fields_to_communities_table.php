<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->string('developer')->nullable()->after('name');
            $table->integer('dld_area_id')->nullable()->after('developer');
            $table->integer('dld_master_project_id')->nullable()->after('dld_area_id');
            $table->smallInteger('zone')->nullable()->after('dld_master_project_id')->comment('1=Non-Freehold 2=Freehold');
            $table->boolean('is_freehold')->nullable()->after('zone');
            $table->jsonb('project_names')->nullable()->after('is_freehold')->comment('Array of sub-project/building name aliases for fuzzy matching');

            $table->index('dld_area_id');
            $table->index('dld_master_project_id');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->dropIndex(['dld_area_id']);
            $table->dropIndex(['dld_master_project_id']);
            $table->dropColumn(['developer', 'dld_area_id', 'dld_master_project_id', 'zone', 'is_freehold', 'project_names']);
        });
    }
};
