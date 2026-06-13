<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'Wati 1' => 'wati_1',
            'Wati 2' => 'wati_2',
            'Wati 3' => 'wati_3',
            'Wati 4' => 'wati_4',
            'wati'   => 'wati_3',
        ];

        foreach ($map as $old => $new) {
            DB::table('whatsapp_campaigns')->where('platform', $old)->update(['platform' => $new]);
            DB::table('whatsapp_imports')->where('source_name', $old)->update(['source_name' => $new]);
        }
    }

    public function down(): void
    {
        // Normalization is not reversible — labels were inconsistently stored before this migration.
    }
};
