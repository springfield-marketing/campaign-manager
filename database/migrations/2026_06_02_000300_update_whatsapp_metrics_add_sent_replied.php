<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->unsignedInteger('sent_count')->default(0)->after('total_messages');
            $table->unsignedInteger('replied_count')->default(0)->after('read_count');
            $table->dropColumn('clicked_count');
        });

        Schema::table('whatsapp_monthly_summaries', function (Blueprint $table): void {
            $table->unsignedInteger('sent_count')->default(0)->after('total_messages');
            $table->unsignedInteger('replied_count')->default(0)->after('read_count');
            $table->dropColumn('clicked_count');
        });

        // Backfill sent_count and replied_count on existing campaigns using the composite index.
        DB::statement("
            UPDATE whatsapp_campaigns wc
            SET
                sent_count    = sub.sent_count,
                replied_count = sub.replied_count
            FROM (
                SELECT
                    whatsapp_campaign_id,
                    sum(case when delivery_status = 'SENT'    then 1 else 0 end) as sent_count,
                    sum(case when delivery_status = 'REPLIED' then 1 else 0 end) as replied_count
                FROM whatsapp_messages
                GROUP BY whatsapp_campaign_id
            ) sub
            WHERE wc.id = sub.whatsapp_campaign_id
        ");
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['sent_count', 'replied_count']);
            $table->unsignedInteger('clicked_count')->default(0);
        });

        Schema::table('whatsapp_monthly_summaries', function (Blueprint $table): void {
            $table->dropColumn(['sent_count', 'replied_count']);
            $table->unsignedInteger('clicked_count')->default(0);
        });
    }
};
