<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS whatsapp_messages_delivery_status_check');

        DB::statement("
            ALTER TABLE whatsapp_messages
            ADD CONSTRAINT whatsapp_messages_delivery_status_check
            CHECK (
                delivery_status IS NULL OR delivery_status IN (
                    'SENT', 'DELIVERED', 'READ', 'REPLIED', 'FAILED', 'STOPPED', 'PENDING'
                )
            ) NOT VALID
        ");

        DB::statement('ALTER TABLE whatsapp_messages VALIDATE CONSTRAINT whatsapp_messages_delivery_status_check');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS whatsapp_messages_delivery_status_check');

        DB::statement("
            ALTER TABLE whatsapp_messages
            ADD CONSTRAINT whatsapp_messages_delivery_status_check
            CHECK (
                delivery_status IS NULL OR delivery_status IN (
                    'SENT', 'DELIVERED', 'READ', 'REPLIED', 'FAILED', 'STOPPED'
                )
            ) NOT VALID
        ");

        DB::statement('ALTER TABLE whatsapp_messages VALIDATE CONSTRAINT whatsapp_messages_delivery_status_check');
    }
};
