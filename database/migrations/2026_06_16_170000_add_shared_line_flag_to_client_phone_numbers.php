<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->boolean('is_shared_line')->default(false)->index();
            $table->string('shared_line_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->dropColumn(['is_shared_line', 'shared_line_note']);
        });
    }
};
