<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'email')) {
            return;
        }

        if (Schema::hasIndex('clients', 'clients_email_index')) {
            Schema::table('clients', function (Blueprint $table): void {
                $table->dropIndex('clients_email_index');
            });
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('email');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'email')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('email')->nullable()->index()->after('full_name');
        });

        DB::statement("
            UPDATE clients
            SET email = primary_emails.email
            FROM (
                SELECT client_id, email
                FROM client_emails
                WHERE is_primary = true
            ) AS primary_emails
            WHERE primary_emails.client_id = clients.id
        ");
    }
};
