<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type');
            $table->string('source')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // No updated_at — interactions are immutable
            $table->index(['client_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_interactions');
    }
};
