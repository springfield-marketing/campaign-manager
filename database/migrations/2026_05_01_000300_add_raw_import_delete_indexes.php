<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_sources', function (Blueprint $table): void {
            $table->index(['channel', 'source_type', 'source_reference'], 'client_sources_import_delete_lookup_idx');
            $table->index('client_phone_number_id', 'client_sources_phone_delete_idx');
            $table->index('client_id', 'client_sources_client_delete_idx');
        });

        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->index('client_id', 'client_phone_numbers_client_delete_idx');
        });

        Schema::table('contact_suppressions', function (Blueprint $table): void {
            $table->index('client_phone_number_id', 'contact_suppressions_phone_delete_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contact_suppressions', function (Blueprint $table): void {
            $table->dropIndex('contact_suppressions_phone_delete_idx');
        });

        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->dropIndex('client_phone_numbers_client_delete_idx');
        });

        Schema::table('client_sources', function (Blueprint $table): void {
            $table->dropIndex('client_sources_client_delete_idx');
            $table->dropIndex('client_sources_phone_delete_idx');
            $table->dropIndex('client_sources_import_delete_lookup_idx');
        });
    }
};
