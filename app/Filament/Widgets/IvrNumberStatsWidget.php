<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IvrNumberStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        // Single query with CASE aggregates — replaces five separate cloned queries.
        // FLOOR((s + 59) / 60) is ceiling-division that works on MySQL and SQLite alike.
        $counts = DB::selectOne("
            SELECT
                COUNT(*) AS total,

                SUM(CASE WHEN unsubscribed_at IS NOT NULL
                      OR EXISTS (
                            SELECT 1 FROM contact_suppressions cs
                            WHERE cs.client_phone_number_id = client_phone_numbers.id
                              AND cs.released_at IS NULL
                              AND (cs.channel IS NULL OR cs.channel = 'ivr')
                          )
                    THEN 1 ELSE 0 END
                ) AS unsubscribed,

                SUM(CASE WHEN EXISTS (
                            SELECT 1 FROM ivr_phone_profiles ipp
                            WHERE ipp.client_phone_number_id = client_phone_numbers.id
                              AND (
                                ipp.usage_status = 'inactive'
                                OR (ipp.cooldown_until IS NOT NULL AND ipp.cooldown_until > NOW())
                              )
                          )
                    THEN 1 ELSE 0 END
                ) AS resting,

                SUM(CASE WHEN EXISTS (
                            SELECT 1 FROM ivr_phone_profiles ipp
                            WHERE ipp.client_phone_number_id = client_phone_numbers.id
                              AND ipp.usage_status = 'dead'
                          )
                    THEN 1 ELSE 0 END
                ) AS not_callable,

                SUM(CASE
                    WHEN unsubscribed_at IS NULL
                     AND NOT EXISTS (
                            SELECT 1 FROM contact_suppressions cs
                            WHERE cs.client_phone_number_id = client_phone_numbers.id
                              AND cs.released_at IS NULL
                              AND (cs.channel IS NULL OR cs.channel = 'ivr')
                          )
                     AND (
                            NOT EXISTS (
                                SELECT 1 FROM ivr_phone_profiles ipp
                                WHERE ipp.client_phone_number_id = client_phone_numbers.id
                            )
                            OR EXISTS (
                                SELECT 1 FROM ivr_phone_profiles ipp
                                WHERE ipp.client_phone_number_id = client_phone_numbers.id
                                  AND ipp.usage_status = 'active'
                                  AND (ipp.cooldown_until IS NULL OR ipp.cooldown_until <= NOW())
                            )
                          )
                     AND EXISTS (
                            SELECT 1 FROM clients c
                            WHERE c.id = client_phone_numbers.client_id
                              AND c.full_name IS NOT NULL
                              AND TRIM(c.full_name) <> ''
                          )
                    THEN 1 ELSE 0 END
                ) AS ready

            FROM client_phone_numbers
            WHERE is_uae = true
              AND normalized_phone LIKE '+9715%'
              AND LENGTH(normalized_phone) = 13
        ");

        $total = (int) $counts->total;
        $ready = (int) $counts->ready;

        $readyRate = $total > 0
            ? number_format(($ready / $total) * 100, 1).'% ready'
            : null;

        return [
            Stat::make('Total IVR Numbers', number_format($total))
                ->icon('heroicon-o-phone')
                ->description('UAE mobile numbers in contacts')
                ->extraAttributes(['x-tooltip.raw' => 'Every UAE mobile number in the system that could potentially receive an IVR call. This is the full pool — it includes numbers that are suppressed, resting, or otherwise not currently callable.']),

            Stat::make('Active Numbers', number_format($ready))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($readyRate)
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that are ready to be called right now — not suppressed, not on a rest period, has a name on the contact, and has not been marked Do Not Call.']),

            Stat::make('Resting', number_format((int) $counts->resting))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->description('Temporarily held back')
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that were called recently and are on a temporary rest before they can be called again. They will become active once the rest period ends.']),

            Stat::make('Not Callable', number_format((int) $counts->not_callable))
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->description('Not eligible for IVR')
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that failed too many times or are otherwise permanently ineligible for IVR. They will not be included in any export.']),

            Stat::make('Do Not Call', number_format((int) $counts->unsubscribed))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->description('Customer opt outs and DND imports')
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that have opted out or been marked Do Not Call — either by the customer, imported from a DND list, or manually suppressed. These are permanently excluded from exports.']),
        ];
    }
}
