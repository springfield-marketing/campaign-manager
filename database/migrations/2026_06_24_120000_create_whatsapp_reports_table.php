<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('fatigue');   // which insight report
            $table->string('status')->default('pending');  // pending|processing|completed|failed
            $table->string('file_name')->nullable();
            $table->string('storage_path')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('total_rows')->default(0);
            $table->bigInteger('processed_rows')->default(0);
            $table->bigInteger('file_size')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_reports');
    }
};
