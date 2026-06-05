<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('email');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('client_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_emails');
    }
};
