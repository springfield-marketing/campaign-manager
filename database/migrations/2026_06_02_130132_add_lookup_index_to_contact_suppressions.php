<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_suppressions', function (Blueprint $table): void {
            // Speeds up the per-row EXISTS check in WhatsApp/IVR number queries:
            // WHERE client_phone_number_id = ? AND channel = ? AND released_at IS NULL
            $table->index(
                ['client_phone_number_id', 'channel', 'released_at'],
                'contact_suppressions_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('contact_suppressions', function (Blueprint $table): void {
            $table->dropIndex('contact_suppressions_lookup_idx');
        });
    }
};
