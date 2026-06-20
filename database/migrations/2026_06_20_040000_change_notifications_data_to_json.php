<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Filament's database notifications query `data->>'format'`, which on Postgres requires a
        // json column — the default Laravel notifications migration ships `data` as text (fine on
        // MySQL, broken on Postgres). Existing rows already hold valid JSON, so the cast is safe.
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
