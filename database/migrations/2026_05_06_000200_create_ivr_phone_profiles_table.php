<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_phone_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_phone_number_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('usage_status')->default('active');
            $table->string('last_call_outcome')->nullable();
            $table->timestamp('last_called_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamps();
        });

        // Backfill: copy existing IVR-specific data from client_phone_numbers
        // Only insert rows where at least one column has a non-default value
        DB::statement("
            INSERT INTO ivr_phone_profiles (client_phone_number_id, usage_status, last_call_outcome, last_called_at, cooldown_until, created_at, updated_at)
            SELECT id, usage_status, last_call_outcome, last_called_at, cooldown_until, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM client_phone_numbers
            WHERE usage_status != 'active'
               OR last_call_outcome IS NOT NULL
               OR last_called_at IS NOT NULL
               OR cooldown_until IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_phone_profiles');
    }
};
