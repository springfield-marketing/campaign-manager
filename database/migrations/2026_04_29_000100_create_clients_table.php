<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('country')->nullable();
            $table->string('nationality')->nullable();
            $table->string('community')->nullable();
            $table->string('resident')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('gender')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
