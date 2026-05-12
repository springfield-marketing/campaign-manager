<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_phone_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_phone_number_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('consecutive_failed_count')->default(0);
            $table->string('last_message_status')->nullable();
            $table->timestamp('last_messaged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_phone_profiles');
    }
};
