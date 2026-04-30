<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_phone_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_phone');
            $table->string('normalized_phone')->unique();
            $table->string('country_code')->nullable();
            $table->string('national_number')->nullable();
            $table->string('detected_country')->nullable();
            $table->boolean('is_uae')->default(false)->index();
            $table->string('usage_status')->default('active')->index();
            $table->string('last_call_outcome')->nullable();
            $table->string('last_source_name')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamp('last_called_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_phone_numbers');
    }
};
