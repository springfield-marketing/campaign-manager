<?php

namespace App\Filament\Widgets;

use App\Modules\WhatsApp\Models\WhatsAppCampaign;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WhatsAppCampaignStatsWidget extends StatsOverviewWidget
{
    public int|string $campaignId;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $campaign = WhatsAppCampaign::query()->find($this->campaignId);

        if (! $campaign) {
            return [];
        }

        $total     = (int) $campaign->total_messages;
        $sent      = (int) $campaign->sent_count;
        $delivered = (int) $campaign->delivered_count;
        $read      = (int) $campaign->read_count;
        $replied   = (int) $campaign->replied_count;
        $failed    = (int) $campaign->failed_count;
        $unsub     = (int) $campaign->unsubscribed_count;

        $deliveryRate = $total > 0
            ? number_format(($delivered / $total) * 100, 1) . '% delivery rate'
            : null;

        $readRate = $delivered > 0
            ? number_format(($read / $delivered) * 100, 1) . '% of delivered'
            : null;

        $failRate = $total > 0
            ? number_format(($failed / $total) * 100, 1) . '% failure rate'
            : null;

        return [
            Stat::make('Total Sent', number_format($total))
                ->icon('heroicon-o-paper-airplane')
                ->extraAttributes(['x-tooltip.raw' => 'Total messages dispatched to WhatsApp for delivery in this campaign.']),

            Stat::make('Delivered', number_format($delivered))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($deliveryRate)
                ->extraAttributes(['x-tooltip.raw' => 'Messages confirmed as delivered to the recipient\'s device — WhatsApp returned a delivery receipt (double grey tick).']),

            Stat::make('Read', number_format($read))
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->description($readRate)
                ->extraAttributes(['x-tooltip.raw' => 'Messages that were opened by the recipient — confirmed by WhatsApp read receipts (double blue tick). Only counts if read receipts are enabled on the recipient\'s account.']),

            Stat::make('Replied', number_format($replied))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->description($total > 0 ? number_format(($replied / $total) * 100, 1) . '% reply rate' : null)
                ->extraAttributes(['x-tooltip.raw' => 'Recipients who sent a reply message back after receiving the campaign message.']),

            Stat::make('Failed', number_format($failed))
                ->icon('heroicon-o-x-circle')
                ->color($failed > 0 ? 'danger' : 'gray')
                ->description($failRate)
                ->extraAttributes(['x-tooltip.raw' => 'Messages that could not be delivered — typically invalid or deleted numbers, accounts that have blocked the sender, or WhatsApp delivery errors.']),

            Stat::make('Unsubscribed', number_format($unsub))
                ->icon('heroicon-o-no-symbol')
                ->color($unsub > 0 ? 'warning' : 'gray')
                ->extraAttributes(['x-tooltip.raw' => 'Recipients who replied STOP or were manually opted out during or after this campaign. They are added to the suppression list and will not receive future messages.']),
        ];
    }
}
