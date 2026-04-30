<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ivr_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('error_type')->nullable();
            $table->text('error_message');
            $table->json('row_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_import_errors');
    }
};
