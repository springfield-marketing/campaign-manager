<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copy every non-empty email from clients.email into client_emails,
        // marking each as the primary address. clients.email is left intact
        // so existing code continues to work during the transition.
        DB::statement("
            INSERT INTO client_emails (client_id, email, is_primary, created_at, updated_at)
            SELECT id, email, true, NOW(), NOW()
            FROM clients
            WHERE email IS NOT NULL
              AND email != ''
        ");
    }

    public function down(): void
    {
        // Remove only the rows that were seeded from clients.email.
        // Any rows added after migration (via the new system) are left alone.
        DB::statement("
            DELETE FROM client_emails ce
            WHERE ce.is_primary = true
              AND EXISTS (
                  SELECT 1 FROM clients c
                  WHERE c.id = ce.client_id
                    AND c.email = ce.email
              )
        ");
    }
};
