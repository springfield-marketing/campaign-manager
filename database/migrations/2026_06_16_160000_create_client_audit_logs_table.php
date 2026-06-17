<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('action'); // 'deleted' | 'merged'
            $table->unsignedBigInteger('client_id'); // the client deleted, or absorbed into target_client_id
            $table->unsignedBigInteger('target_client_id')->nullable(); // merge destination, null for plain deletes
            $table->text('reason')->nullable();
            $table->string('performed_by')->nullable();
            $table->json('snapshot'); // full pre-action dump: client row, phones, related record counts
            $table->timestamps();

            $table->index('client_id');
            $table->index('target_client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_audit_logs');
    }
};
