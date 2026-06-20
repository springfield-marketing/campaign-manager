<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            // Stamped when a raw IVR import re-introduces a number that is already on the
            // Do-Not-Call list (active IVR suppression). Surfaces "this number came back in a
            // new list after you suppressed it" so it can be audited and kept off call lists.
            $table->timestamp('reentered_while_suppressed_at')->nullable();
        });

        // Partial index — only the handful of flagged rows, for the audit filter.
        \Illuminate\Support\Facades\DB::statement('CREATE INDEX client_phone_numbers_reentered_idx ON client_phone_numbers (reentered_while_suppressed_at) WHERE reentered_while_suppressed_at IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('client_phone_numbers', function (Blueprint $table): void {
            $table->dropColumn('reentered_while_suppressed_at');
        });
    }
};
