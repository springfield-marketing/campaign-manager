<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_phone_number_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->nullable()->index();
            $table->string('reason');
            $table->json('context')->nullable();
            $table->timestamp('suppressed_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_suppressions');
    }
};
