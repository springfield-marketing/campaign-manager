<?php

namespace App\Modules\WhatsApp\Support;

use App\Modules\WhatsApp\Enums\WhatsAppPlatform;
use App\Modules\WhatsApp\Models\WhatsAppSettings;
use Illuminate\Support\Facades\DB;

class WhatsAppBatchProfileUpdater
{
    public function run(array $ids = []): void
    {
        $this->updateProfiles($ids);
        $this->updateSuppressions($ids);
        $this->updateLeadFlags($ids);
    }

    // -------------------------------------------------------------------------
    // Profile upsert — determines usage_status / cooldown_until for each number
    // -------------------------------------------------------------------------

    private function updateProfiles(array $ids): void
    {
        $idFilter = $this->idFilter('wm.client_phone_number_id', $ids);

        $settings = WhatsAppSettings::current();

        $hardFailThreshold = $settings->hard_fail_threshold;
        // Quarantine threshold: a number messaged MORE than this many times that has never once
        // been delivered is parked in 'quarantine' for manual review (reuses the otherwise-unused
        // bulk_dead_threshold setting, currently 10).
        $quarantineMinMessages = $settings->bulk_dead_threshold;
        // The single cooldown window (days): a number messaged within this many days is on
        // cooldown. All other historical cooldown variables (no-engagement, quality-hold,
        // experiment, regional) were removed in favour of this one rule.
        $cooldownDays      = $settings->min_days_between_sends;

        DB::statement("
            WITH classified AS (
                SELECT
                    wm.client_phone_number_id,
                    ROW_NUMBER() OVER (
                        PARTITION BY wm.client_phone_number_id
                        ORDER BY wm.scheduled_at DESC, wm.id DESC
                    ) AS rn,
                    wm.delivery_status,
                    COALESCE(wm.failure_reason, '') AS failure_reason,
                    wm.scheduled_at,
                    -- fail_class is kept only to drive the unchanged 'dead' classification
                    -- (consecutive hard fails / bulk-dead). It no longer feeds any cooldown.
                    CASE
                        WHEN wm.delivery_status != 'FAILED' THEN 'delivered'
                        WHEN wm.failure_reason LIKE '%chosen to stop receiving marketing messages%' THEN 'opted_out'
                        WHEN wm.failure_reason LIKE '%retry again in a few days%'                   THEN 'quality_hold'
                        WHEN wm.failure_reason LIKE '%part of an experiment%'                        THEN 'experiment'
                        WHEN wm.failure_reason LIKE '%US recipients%'                                THEN 'regional'
                        WHEN wm.failure_reason LIKE '%Insufficient credit balance%'
                          OR wm.failure_reason LIKE '%Something went wrong%'
                          OR wm.failure_reason LIKE '%OAuthException%'                               THEN 'system_error'
                        ELSE 'hard_fail'
                    END AS fail_class
                FROM whatsapp_messages wm
                WHERE 1=1 {$idFilter}
            ),
            streak_cutoff AS (
                -- First row (newest-first) that breaks the consecutive hard-fail streak.
                -- system_error is transparent; everything else (delivered, opts, holds) ends it.
                SELECT client_phone_number_id, MIN(rn) AS first_non_system_rn
                FROM classified
                WHERE fail_class NOT IN ('hard_fail', 'system_error')
                GROUP BY client_phone_number_id
            ),
            aggregated AS (
                SELECT
                    c.client_phone_number_id,

                    COUNT(*) FILTER (
                        WHERE c.fail_class = 'hard_fail'
                          AND c.rn < COALESCE(sc.first_non_system_rn, 2147483647)
                    )                                                                   AS consecutive_hard_fail_count,

                    COUNT(*)                                                             AS total_messages,
                    COUNT(*) FILTER (WHERE c.delivery_status IN ('DELIVERED','READ','REPLIED')) AS delivered_count,
                    COUNT(*) FILTER (WHERE c.delivery_status IN ('READ','REPLIED'))     AS read_count,

                    MAX(CASE WHEN c.rn = 1 THEN c.delivery_status END)                 AS last_message_status,
                    MAX(CASE WHEN c.rn = 1 THEN NULLIF(c.failure_reason, '') END)       AS last_failure_reason,
                    MAX(CASE WHEN c.rn = 1 THEN c.scheduled_at END)                    AS last_messaged_at

                FROM classified c
                LEFT JOIN streak_cutoff sc ON sc.client_phone_number_id = c.client_phone_number_id
                GROUP BY c.client_phone_number_id
            ),
            profiles AS (
                SELECT
                    a.client_phone_number_id,
                    a.consecutive_hard_fail_count,
                    a.last_message_status,
                    a.last_failure_reason,
                    a.last_messaged_at,

                    -- usage_status precedence (first match wins):
                    --   manually_dead = a reviewer pushed it to dead from quarantine; always wins
                    --                   and is never auto-cleared.
                    --   'quarantine'  = messaged > {$quarantineMinMessages} times and NEVER once
                    --                   delivered. A strong dead candidate, parked for manual
                    --                   review rather than auto-killed.
                    --   'dead'        = >= {$hardFailThreshold} consecutive hard fails AND never
                    --                   read/replied. A read/reply receipt proves a real, reachable
                    --                   person, so those are spared. Recency-based / self-healing.
                    --   'cooldown'    = messaged within the last {$cooldownDays} days (expires
                    --                   {$cooldownDays} days after the last message).
                    CASE
                        WHEN COALESCE(ep.manually_dead, false) THEN 'dead'
                        WHEN a.total_messages > {$quarantineMinMessages} AND a.delivered_count = 0 THEN 'quarantine'
                        WHEN a.consecutive_hard_fail_count >= {$hardFailThreshold} AND a.read_count = 0 THEN 'dead'
                        WHEN {$cooldownDays} > 0
                          AND a.last_messaged_at > NOW() - ({$cooldownDays} * INTERVAL '1 day')
                        THEN 'cooldown'
                        ELSE 'active'
                    END AS usage_status,

                    CASE
                        WHEN COALESCE(ep.manually_dead, false) THEN NULL
                        WHEN a.total_messages > {$quarantineMinMessages} AND a.delivered_count = 0 THEN NULL
                        WHEN a.consecutive_hard_fail_count >= {$hardFailThreshold} AND a.read_count = 0 THEN NULL
                        WHEN {$cooldownDays} > 0
                          AND a.last_messaged_at > NOW() - ({$cooldownDays} * INTERVAL '1 day')
                        THEN a.last_messaged_at + ({$cooldownDays} * INTERVAL '1 day')
                        ELSE NULL
                    END AS cooldown_until

                FROM aggregated a
                LEFT JOIN whatsapp_phone_profiles ep ON ep.client_phone_number_id = a.client_phone_number_id
            )
            INSERT INTO whatsapp_phone_profiles (
                client_phone_number_id,
                consecutive_hard_fail_count,
                last_message_status,
                last_failure_reason,
                last_messaged_at,
                usage_status,
                cooldown_until,
                created_at,
                updated_at
            )
            SELECT
                client_phone_number_id,
                consecutive_hard_fail_count,
                last_message_status,
                last_failure_reason,
                last_messaged_at,
                usage_status,
                cooldown_until,
                NOW(),
                NOW()
            FROM profiles
            ON CONFLICT (client_phone_number_id) DO UPDATE SET
                consecutive_hard_fail_count = EXCLUDED.consecutive_hard_fail_count,
                last_message_status         = EXCLUDED.last_message_status,
                last_failure_reason         = EXCLUDED.last_failure_reason,
                last_messaged_at            = EXCLUDED.last_messaged_at,
                usage_status                = EXCLUDED.usage_status,
                cooldown_until              = EXCLUDED.cooldown_until,
                updated_at                  = NOW()
        ");
    }

    // -------------------------------------------------------------------------
    // Suppression insert — opted-out and spam numbers only
    // -------------------------------------------------------------------------

    private function updateSuppressions(array $ids): void
    {
        $idFilter   = $this->idFilter('wm.client_phone_number_id', $ids);
        $watiList   = "'" . implode("','", WhatsAppPlatform::watiValues()) . "'";

        DB::statement("
            INSERT INTO contact_suppressions
                (client_phone_number_id, channel, reason, suppressed_at, context, created_at, updated_at)
            SELECT
                sub.client_phone_number_id,
                'whatsapp',
                sub.reason,
                NOW(),
                '{}'::jsonb,
                NOW(),
                NOW()
            FROM (
                SELECT DISTINCT client_phone_number_id, reason
                FROM (
                    SELECT wm.client_phone_number_id, 'opted_out' AS reason
                    FROM whatsapp_messages wm
                    WHERE wm.delivery_status = 'FAILED'
                      AND wm.failure_reason LIKE '%chosen to stop receiving marketing messages%'
                      {$idFilter}

                    UNION ALL

                    -- Wati: reply 3 = customer unsubscribed
                    SELECT wm.client_phone_number_id, 'customer_unsubscribed' AS reason
                    FROM whatsapp_messages wm
                    JOIN whatsapp_campaigns wc ON wc.id = wm.whatsapp_campaign_id
                    WHERE wm.has_quick_replies = true
                      AND wm.quick_reply_3 IS NOT NULL
                      AND wm.quick_reply_3 != ''
                      AND wc.platform IN ({$watiList})
                      {$idFilter}

                    UNION ALL

                    -- Non-Wati: reply 3 = reported spam
                    SELECT wm.client_phone_number_id, 'reported_spam' AS reason
                    FROM whatsapp_messages wm
                    JOIN whatsapp_campaigns wc ON wc.id = wm.whatsapp_campaign_id
                    WHERE wm.has_quick_replies = true
                      AND wm.quick_reply_3 IS NOT NULL
                      AND wm.quick_reply_3 != ''
                      AND (wc.platform IS NULL OR wc.platform NOT IN ({$watiList}))
                      {$idFilter}
                ) candidates
            ) sub
            WHERE NOT EXISTS (
                SELECT 1
                FROM contact_suppressions cs
                WHERE cs.client_phone_number_id = sub.client_phone_number_id
                  AND cs.channel  = 'whatsapp'
                  AND cs.reason   = sub.reason
                  AND cs.released_at IS NULL
            )
        ");
    }

    // -------------------------------------------------------------------------
    // Lead flag update — dead / spam → false; quick-reply lead → true
    // -------------------------------------------------------------------------

    private function updateLeadFlags(array $ids): void
    {
        $idFilter        = $this->idFilter('m.client_phone_number_id', $ids);
        $profileIdFilter = $this->idFilter('wpp.client_phone_number_id', $ids);

        DB::statement("
            UPDATE client_phone_numbers
            SET is_whatsapp_lead = lead_data.new_value
            FROM (
                SELECT
                    m.client_phone_number_id,
                    CASE
                        WHEN wpp.usage_status = 'dead'
                          OR BOOL_OR(
                              m.has_quick_replies
                              AND m.quick_reply_3 IS NOT NULL AND m.quick_reply_3 != ''
                          )
                        THEN false
                        WHEN BOOL_OR(
                            m.has_quick_replies
                            AND (m.quick_reply_3 IS NULL OR m.quick_reply_3 = '')
                            AND (
                                (m.quick_reply_1 IS NOT NULL AND m.quick_reply_1 != '')
                                OR (m.quick_reply_2 IS NOT NULL AND m.quick_reply_2 != '')
                            )
                        )
                        THEN true
                        ELSE NULL
                    END AS new_value
                FROM whatsapp_messages m
                LEFT JOIN whatsapp_phone_profiles wpp
                    ON wpp.client_phone_number_id = m.client_phone_number_id
                    {$profileIdFilter}
                WHERE 1=1 {$idFilter}
                GROUP BY m.client_phone_number_id, wpp.usage_status
            ) lead_data
            WHERE client_phone_numbers.id = lead_data.client_phone_number_id
              AND lead_data.new_value IS NOT NULL
              AND client_phone_numbers.is_whatsapp_lead IS DISTINCT FROM lead_data.new_value
        ");
    }

    // -------------------------------------------------------------------------

    private function idFilter(string $column, array $ids): string
    {
        if (empty($ids)) {
            return '';
        }

        $list = implode(',', array_map('intval', $ids));

        return "AND {$column} = ANY(ARRAY[{$list}]::bigint[])";
    }
}
