<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_export_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('record_count')->default(0);
            $table->json('filters_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_export_batch_numbers', function (Blueprint $table): void {
            $table->foreignId('whatsapp_export_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_phone_number_id')->constrained()->cascadeOnDelete();

            $table->primary(['whatsapp_export_batch_id', 'client_phone_number_id']);
            $table->index('client_phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_export_batch_numbers');
        Schema::dropIfExists('whatsapp_export_batches');
    }
};
