<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class WhatsAppNumberStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        $counts = DB::selectOne("
            SELECT
                COUNT(*) AS total,

                SUM(CASE
                    WHEN EXISTS (
                        SELECT 1 FROM whatsapp_phone_profiles wpp
                        WHERE wpp.client_phone_number_id = client_phone_numbers.id
                          AND wpp.usage_status = 'active'
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM contact_suppressions cs
                        WHERE cs.client_phone_number_id = client_phone_numbers.id
                          AND cs.channel = 'whatsapp'
                          AND cs.released_at IS NULL
                    )
                    THEN 1 ELSE 0 END
                ) AS active,

                SUM(CASE
                    WHEN EXISTS (
                        SELECT 1 FROM whatsapp_phone_profiles wpp
                        WHERE wpp.client_phone_number_id = client_phone_numbers.id
                          AND wpp.usage_status = 'cooldown'
                    )
                    THEN 1 ELSE 0 END
                ) AS cooldown,

                SUM(CASE
                    WHEN EXISTS (
                        SELECT 1 FROM whatsapp_phone_profiles wpp
                        WHERE wpp.client_phone_number_id = client_phone_numbers.id
                          AND wpp.usage_status = 'dead'
                    )
                    THEN 1 ELSE 0 END
                ) AS dead,

                SUM(CASE
                    WHEN NOT EXISTS (
                        SELECT 1 FROM whatsapp_phone_profiles wpp
                        WHERE wpp.client_phone_number_id = client_phone_numbers.id
                    )
                    THEN 1 ELSE 0 END
                ) AS never_messaged,

                SUM(CASE
                    WHEN EXISTS (
                        SELECT 1 FROM contact_suppressions cs
                        WHERE cs.client_phone_number_id = client_phone_numbers.id
                          AND cs.channel = 'whatsapp'
                          AND cs.released_at IS NULL
                    )
                    THEN 1 ELSE 0 END
                ) AS unsubscribed

            FROM client_phone_numbers
            WHERE (
                (is_uae = true AND national_number LIKE '5%')
                OR is_uae = false
            )
        ");

        $total  = (int) $counts->total;
        $active = (int) $counts->active;

        $activeRate = $total > 0
            ? number_format(($active / $total) * 100, 1) . '% active'
            : null;

        return [
            Stat::make('Total WhatsApp Numbers', number_format($total))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->description('UAE mobile + non-UAE numbers in contacts'),

            Stat::make('Active', number_format($active))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($activeRate),

            Stat::make('Cooldown', number_format((int) $counts->cooldown))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->description('Temporarily held back'),

            Stat::make('Never Messaged', number_format((int) $counts->never_messaged))
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->description('No campaign history'),

            Stat::make('Unsubscribed', number_format((int) $counts->unsubscribed))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->description('Opted out of WhatsApp'),
        ];
    }
}
