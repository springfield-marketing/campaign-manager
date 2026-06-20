<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            // IMP-003: marks clients whose name is an organisation (developer/bank/LLC) rather
            // than a person. Such records are not marketing contacts; they are hidden from the
            // Contacts list by default. Backfilled by `clients:flag-institutions`.
            $table->boolean('is_institution')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('is_institution');
        });
    }
};
