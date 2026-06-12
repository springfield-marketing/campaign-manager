<?php

namespace App\Modules\IVR\Support;

use App\Modules\IVR\Models\IvrSettings;
use Illuminate\Support\Facades\DB;

class IvrBatchEligibilityUpdater
{
    /**
     * Recompute ivr_phone_profiles for the given phone number IDs in one SQL pass.
     * Pass an empty array to process every number that has call records.
     *
     * @param array<int> $ids
     */
    public function run(array $ids = []): void
    {
        $settings     = IvrSettings::current();
        $n            = (int) config('ivr.eligibility.inactive_after_consecutive_no_answers', 5);
        $answeredDays = (int) $settings->cooldown_answered_days;
        $missedDays   = (int) $settings->cooldown_missed_days;

        $idFilter = $this->idFilter('ivr_call_records.client_phone_number_id', $ids);

        DB::statement("
            WITH recent_calls AS (
                -- Rank every call record per number newest-first.
                SELECT
                    client_phone_number_id,
                    call_time,
                    call_status,
                    dtmf_outcome,
                    ROW_NUMBER() OVER (
                        PARTITION BY client_phone_number_id
                        ORDER BY call_time DESC, id DESC
                    ) AS rn
                FROM ivr_call_records
                WHERE 1=1 {$idFilter}
            ),
            top_n AS (
                -- Only consider the last N records — matching the PHP analyser's limit.
                SELECT * FROM recent_calls WHERE rn <= {$n}
            ),
            first_answer AS (
                -- Position of the most-recent answered call within the window.
                SELECT client_phone_number_id, MIN(rn) AS rn
                FROM top_n
                WHERE LOWER(call_status) = 'answered'
                GROUP BY client_phone_number_id
            ),
            miss_streak AS (
                -- Non-answered calls before the first answered call = consecutive miss count.
                -- If no answered call exists in the window, all non-answered rows count.
                SELECT
                    t.client_phone_number_id,
                    COUNT(*) AS consecutive_misses
                FROM top_n t
                LEFT JOIN first_answer fa ON fa.client_phone_number_id = t.client_phone_number_id
                WHERE LOWER(t.call_status) != 'answered'
                  AND t.rn < COALESCE(fa.rn, {$n} + 1)
                GROUP BY t.client_phone_number_id
            ),
            last_call AS (
                SELECT * FROM recent_calls WHERE rn = 1
            ),
            profiles AS (
                SELECT
                    lc.client_phone_number_id,
                    lc.dtmf_outcome AS last_call_outcome,
                    lc.call_time    AS last_called_at,
                    CASE
                        WHEN LOWER(lc.call_status) = 'answered'
                        THEN lc.call_time + ({$answeredDays} * INTERVAL '1 day')
                        ELSE lc.call_time + ({$missedDays}   * INTERVAL '1 day')
                    END AS cooldown_until,
                    CASE
                        WHEN COALESCE(ms.consecutive_misses, 0) >= {$n}
                          OR (
                              CASE
                                  WHEN LOWER(lc.call_status) = 'answered'
                                  THEN lc.call_time + ({$answeredDays} * INTERVAL '1 day')
                                  ELSE lc.call_time + ({$missedDays}   * INTERVAL '1 day')
                              END > NOW()
                          )
                        THEN 'inactive'
                        ELSE 'active'
                    END AS usage_status
                FROM last_call lc
                LEFT JOIN miss_streak ms ON ms.client_phone_number_id = lc.client_phone_number_id
            )
            INSERT INTO ivr_phone_profiles (
                client_phone_number_id,
                usage_status,
                last_call_outcome,
                last_called_at,
                cooldown_until,
                created_at,
                updated_at
            )
            SELECT
                client_phone_number_id,
                usage_status,
                last_call_outcome,
                last_called_at,
                cooldown_until,
                NOW(),
                NOW()
            FROM profiles
            ON CONFLICT (client_phone_number_id) DO UPDATE SET
                usage_status      = EXCLUDED.usage_status,
                last_call_outcome = EXCLUDED.last_call_outcome,
                last_called_at    = EXCLUDED.last_called_at,
                cooldown_until    = EXCLUDED.cooldown_until,
                updated_at        = NOW()
        ");
    }

    private function idFilter(string $column, array $ids): string
    {
        if (empty($ids)) {
            return '';
        }

        $list = implode(',', array_map('intval', $ids));

        return "AND {$column} = ANY(ARRAY[{$list}]::bigint[])";
    }
}
