<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\WhatsAppNumbers\Pages\ListWhatsAppNumbers;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WhatsAppNumberStatsWidget extends StatsOverviewWidget
{
    // Lets the final "Matching filters" stat read the live, filtered table query so it can
    // sit in the same grid as the global stats instead of in a separate widget.
    use InteractsWithPageTable;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    public const CACHE_KEY = 'whatsapp-number-stats-totals';

    public const CACHE_TTL = 300;

    protected function getTablePage(): string
    {
        return ListWhatsAppNumbers::class;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        // Global totals are cached and warmed by `stats:warm-number-widgets` every minute, so the
        // heavy aggregate never blocks a page load. Cast to object for property access (a cached
        // stdClass can deserialize as an incomplete class under some cache drivers).
        $counts = (object) Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => self::globalTotals());

        return $this->renderStats($counts);
    }

    /** The heavy global aggregate — shared by the widget cache and the warmer command. */
    public static function globalTotals(): array
    {
        return (array) DB::selectOne("
            SELECT
                COUNT(*) AS total,

                SUM(CASE
                    WHEN EXISTS (
                        SELECT 1 FROM whatsapp_phone_profiles wpp
                        WHERE wpp.client_phone_number_id = client_phone_numbers.id
                          AND (
                              wpp.usage_status = 'active'
                              OR (wpp.usage_status = 'cooldown' AND (wpp.cooldown_until IS NULL OR wpp.cooldown_until <= NOW()))
                          )
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
                          AND wpp.cooldown_until IS NOT NULL
                          AND wpp.cooldown_until > NOW()
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
    }

    private function renderStats(object $counts): array
    {
        $total  = (int) $counts->total;
        $active = (int) $counts->active;

        $activeRate = $total > 0
            ? number_format(($active / $total) * 100, 1) . '% active'
            : null;

        return [
            Stat::make('Total WhatsApp Numbers', number_format($total))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->description('UAE mobile + non-UAE numbers in contacts')
                ->extraAttributes(['x-tooltip.raw' => 'Every phone number in the system that can potentially receive a WhatsApp message — UAE mobile numbers starting with 5, plus all international numbers.']),

            Stat::make('Active', number_format($active))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($activeRate)
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that have been messaged before and are ready to receive another message. Does not include anyone who has unsubscribed.']),

            Stat::make('Cooldown', number_format((int) $counts->cooldown))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->description('Temporarily held back')
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that were messaged recently and are on a short break before they can be contacted again.']),

            Stat::make('Never Messaged', number_format((int) $counts->never_messaged))
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->description('No campaign history')
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that exist in contacts but have never appeared in any WhatsApp campaign. Fresh numbers with no messaging history.']),

            Stat::make('Unsubscribed', number_format((int) $counts->unsubscribed))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->description('Opted out of WhatsApp')
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that have opted out or been manually suppressed. These are never included in exports and will not be contacted.']),

            Stat::make('Matching filters', number_format($this->getPageTableQuery()->count()))
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->description('Numbers matching the filters currently applied to the table')
                ->extraAttributes(['x-tooltip.raw' => 'How many numbers match the filters you have applied to the table right now. Updates as you change filters — unlike the totals above, which are for the whole database.']),
        ];
    }
}
