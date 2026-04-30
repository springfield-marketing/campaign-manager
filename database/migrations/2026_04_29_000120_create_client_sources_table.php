<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_phone_number_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->index();
            $table->string('source_type')->index();
            $table->string('source_name')->nullable()->index();
            $table->string('source_file_name')->nullable();
            $table->string('source_reference')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_sources');
    }
};
