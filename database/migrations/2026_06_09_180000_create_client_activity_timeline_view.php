<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS client_activity_timeline');

        DB::statement(<<<'SQL'
            CREATE VIEW client_activity_timeline AS
            SELECT
                'interaction:' || ci.id::text AS id,
                ci.client_id,
                ci.created_at AS activity_at,
                'manual'::varchar AS channel,
                ci.type AS activity_type,
                COALESCE(NULLIF(ci.source, ''), 'Manual note') AS title,
                NULL::varchar AS status,
                ci.description AS detail,
                NULL::varchar AS phone_number,
                NULL::varchar AS campaign_name,
                NULL::varchar AS campaign_reference,
                ci.id AS source_id
            FROM client_interactions ci

            UNION ALL

            SELECT
                'ivr:' || cr.id::text AS id,
                cpn.client_id,
                COALESCE(cr.call_time, cr.created_at) AS activity_at,
                'ivr'::varchar AS channel,
                'ivr_campaign'::varchar AS activity_type,
                COALESCE(NULLIF(ic.name, ''), NULLIF(ic.external_campaign_id, ''), 'IVR Campaign') AS title,
                cr.call_status AS status,
                concat_ws(
                    ' | ',
                    NULLIF('Outcome: ' || COALESCE(cr.dtmf_outcome, ''), 'Outcome: '),
                    NULLIF('Disposition: ' || COALESCE(cr.disposition, ''), 'Disposition: '),
                    NULLIF('Sub disposition: ' || COALESCE(cr.sub_disposition, ''), 'Sub disposition: ')
                ) AS detail,
                cpn.normalized_phone AS phone_number,
                ic.name AS campaign_name,
                ic.external_campaign_id AS campaign_reference,
                cr.id AS source_id
            FROM ivr_call_records cr
            JOIN client_phone_numbers cpn ON cpn.id = cr.client_phone_number_id
            LEFT JOIN ivr_campaigns ic ON ic.id = cr.ivr_campaign_id
            WHERE cpn.client_id IS NOT NULL

            UNION ALL

            SELECT
                'whatsapp:' || wm.id::text AS id,
                cpn.client_id,
                COALESCE(wm.scheduled_at, wm.created_at) AS activity_at,
                'whatsapp'::varchar AS channel,
                'whatsapp_campaign'::varchar AS activity_type,
                COALESCE(NULLIF(wc.name, ''), NULLIF(wm.template_name, ''), 'WhatsApp Campaign') AS title,
                wm.delivery_status AS status,
                concat_ws(
                    ' | ',
                    NULLIF('Template: ' || COALESCE(wm.template_name, ''), 'Template: '),
                    NULLIF('Failure: ' || COALESCE(wm.failure_reason, ''), 'Failure: '),
                    CASE WHEN wm.clicked THEN 'Clicked' ELSE NULL END,
                    CASE WHEN wm.has_quick_replies THEN concat_ws(', ', wm.quick_reply_1, wm.quick_reply_2, wm.quick_reply_3) ELSE NULL END
                ) AS detail,
                cpn.normalized_phone AS phone_number,
                wc.name AS campaign_name,
                wc.name AS campaign_reference,
                wm.id AS source_id
            FROM whatsapp_messages wm
            JOIN client_phone_numbers cpn ON cpn.id = wm.client_phone_number_id
            LEFT JOIN whatsapp_campaigns wc ON wc.id = wm.whatsapp_campaign_id
            WHERE cpn.client_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS client_activity_timeline');
    }
};
