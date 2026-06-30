<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IvrNumbers\Pages\ListIvrNumbers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IvrNumberStatsWidget extends StatsOverviewWidget
{
    // Lets the final "Matching filters" stat read the live, filtered table query so it can
    // sit in the same grid as the global stats instead of in a separate widget.
    use InteractsWithPageTable;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    public const CACHE_KEY = 'ivr-number-stats-totals';

    public const CACHE_TTL = 300;

    protected function getTablePage(): string
    {
        return ListIvrNumbers::class;
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

                -- Do Not Call: suppressed or opted-out (checked first, highest priority)
                SUM(CASE WHEN unsubscribed_at IS NOT NULL
                      OR EXISTS (
                            SELECT 1 FROM contact_suppressions cs
                            WHERE cs.client_phone_number_id = client_phone_numbers.id
                              AND cs.released_at IS NULL
                              AND (cs.channel IS NULL OR cs.channel = 'ivr')
                          )
                    THEN 1 ELSE 0 END
                ) AS unsubscribed,

                -- Resting: on cooldown, NOT suppressed (suppressed numbers excluded to avoid double-count)
                SUM(CASE
                    WHEN unsubscribed_at IS NULL
                     AND NOT EXISTS (
                            SELECT 1 FROM contact_suppressions cs
                            WHERE cs.client_phone_number_id = client_phone_numbers.id
                              AND cs.released_at IS NULL
                              AND (cs.channel IS NULL OR cs.channel = 'ivr')
                          )
                     AND EXISTS (
                            SELECT 1 FROM ivr_phone_profiles ipp
                            WHERE ipp.client_phone_number_id = client_phone_numbers.id
                              AND (
                                ipp.usage_status = 'inactive'
                                OR (ipp.cooldown_until IS NOT NULL AND ipp.cooldown_until > NOW())
                              )
                          )
                    THEN 1 ELSE 0 END
                ) AS resting,

                -- No Name: eligible by calling status but missing a contact name — needs data enrichment
                SUM(CASE
                    WHEN unsubscribed_at IS NULL
                     AND NOT EXISTS (
                            SELECT 1 FROM contact_suppressions cs
                            WHERE cs.client_phone_number_id = client_phone_numbers.id
                              AND cs.released_at IS NULL
                              AND (cs.channel IS NULL OR cs.channel = 'ivr')
                          )
                     AND NOT EXISTS (
                            SELECT 1 FROM ivr_phone_profiles ipp
                            WHERE ipp.client_phone_number_id = client_phone_numbers.id
                              AND (
                                ipp.usage_status = 'inactive'
                                OR (ipp.cooldown_until IS NOT NULL AND ipp.cooldown_until > NOW())
                              )
                          )
                     AND NOT EXISTS (
                            SELECT 1 FROM clients c
                            WHERE c.id = client_phone_numbers.client_id
                              AND c.full_name IS NOT NULL
                              AND TRIM(c.full_name) <> ''
                          )
                    THEN 1 ELSE 0 END
                ) AS no_name,

                -- Active (ready): not suppressed, not resting, has a name
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
    }

    private function renderStats(object $counts): array
    {
        $total   = (int) $counts->total;
        $ready   = (int) $counts->ready;
        $noName  = (int) $counts->no_name;

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

            Stat::make('No Name', number_format($noName))
                ->icon('heroicon-o-user')
                ->color('gray')
                ->description('Need data enrichment')
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that are not suppressed and not on a rest period, but have no contact name — they cannot be included in an export until the contact is enriched.']),

            Stat::make('Do Not Call', number_format((int) $counts->unsubscribed))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->description('Customer opt outs and DND imports')
                ->extraAttributes(['x-tooltip.raw' => 'Numbers that have opted out or been marked Do Not Call — either by the customer, imported from a DND list, or manually suppressed. These are permanently excluded from exports.']),

            Stat::make('Matching filters', number_format($this->getPageTableQuery()->count()))
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->description('Numbers matching the filters currently applied to the table')
                ->extraAttributes(['x-tooltip.raw' => 'How many numbers match the filters you have applied to the table right now. Updates as you change filters — unlike the totals above, which are for the whole database.']),
        ];
    }
}
