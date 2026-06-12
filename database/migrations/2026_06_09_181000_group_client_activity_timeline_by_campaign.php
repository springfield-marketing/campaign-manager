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
                'ivr_campaign:' || COALESCE(cr.ivr_campaign_id::text, 'none') || ':' || cpn.id::text AS id,
                cpn.client_id,
                max(COALESCE(cr.call_time, cr.created_at)) AS activity_at,
                'ivr'::varchar AS channel,
                'ivr_campaign'::varchar AS activity_type,
                COALESCE(NULLIF(ic.name, ''), NULLIF(ic.external_campaign_id, ''), 'IVR Campaign') AS title,
                string_agg(DISTINCT cr.call_status, ', ' ORDER BY cr.call_status) AS status,
                concat_ws(
                    ' | ',
                    count(*)::text || ' call' || CASE WHEN count(*) = 1 THEN '' ELSE 's' END,
                    NULLIF('Outcomes: ' || string_agg(DISTINCT cr.dtmf_outcome, ', ' ORDER BY cr.dtmf_outcome) FILTER (WHERE cr.dtmf_outcome IS NOT NULL AND cr.dtmf_outcome <> ''), 'Outcomes: '),
                    NULLIF('Dispositions: ' || string_agg(DISTINCT cr.disposition, ', ' ORDER BY cr.disposition) FILTER (WHERE cr.disposition IS NOT NULL AND cr.disposition <> ''), 'Dispositions: ')
                ) AS detail,
                cpn.normalized_phone AS phone_number,
                ic.name AS campaign_name,
                ic.external_campaign_id AS campaign_reference,
                min(cr.id) AS source_id
            FROM ivr_call_records cr
            JOIN client_phone_numbers cpn ON cpn.id = cr.client_phone_number_id
            LEFT JOIN ivr_campaigns ic ON ic.id = cr.ivr_campaign_id
            WHERE cpn.client_id IS NOT NULL
            GROUP BY cpn.client_id, cpn.id, cpn.normalized_phone, cr.ivr_campaign_id, ic.name, ic.external_campaign_id

            UNION ALL

            SELECT
                'whatsapp_campaign:' || wm.whatsapp_campaign_id::text || ':' || cpn.id::text AS id,
                cpn.client_id,
                max(COALESCE(wm.scheduled_at, wm.created_at)) AS activity_at,
                'whatsapp'::varchar AS channel,
                'whatsapp_campaign'::varchar AS activity_type,
                COALESCE(NULLIF(wc.name, ''), 'WhatsApp Campaign') AS title,
                string_agg(DISTINCT wm.delivery_status, ', ' ORDER BY wm.delivery_status) AS status,
                concat_ws(
                    ' | ',
                    count(*)::text || ' message' || CASE WHEN count(*) = 1 THEN '' ELSE 's' END,
                    NULLIF('Templates: ' || string_agg(DISTINCT wm.template_name, ', ' ORDER BY wm.template_name) FILTER (WHERE wm.template_name IS NOT NULL AND wm.template_name <> ''), 'Templates: '),
                    NULLIF('Failures: ' || string_agg(DISTINCT wm.failure_reason, ', ' ORDER BY wm.failure_reason) FILTER (WHERE wm.failure_reason IS NOT NULL AND wm.failure_reason <> ''), 'Failures: '),
                    CASE WHEN bool_or(wm.clicked) THEN 'Clicked' ELSE NULL END
                ) AS detail,
                cpn.normalized_phone AS phone_number,
                wc.name AS campaign_name,
                wc.name AS campaign_reference,
                min(wm.id) AS source_id
            FROM whatsapp_messages wm
            JOIN client_phone_numbers cpn ON cpn.id = wm.client_phone_number_id
            LEFT JOIN whatsapp_campaigns wc ON wc.id = wm.whatsapp_campaign_id
            WHERE cpn.client_id IS NOT NULL
            GROUP BY cpn.client_id, cpn.id, cpn.normalized_phone, wm.whatsapp_campaign_id, wc.name
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS client_activity_timeline');
    }
};
