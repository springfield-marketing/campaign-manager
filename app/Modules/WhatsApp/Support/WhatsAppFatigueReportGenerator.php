<?php

namespace App\Modules\WhatsApp\Support;

use App\Modules\WhatsApp\Models\WhatsAppReport;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Contact-Fatigue report. One CSV row per WhatsApp number messaged within the window.
 *
 * The window is $report->window_from .. now (null window_from = all-time / lifetime). It drives
 * total_campaigns, reply_rate, read_rate and the non-engagement term. The "current pressure" term
 * (campaigns in the last 30 days) is always relative to now, so even a lifetime report reflects
 * whether we are still messaging them right now.
 *
 *   over_messaging = min(campaigns_last_30d / 10, 1)
 *   non_engagement = campaigns_without_reply / total_campaigns(window)
 *   fatigue        = round(100 * (0.55*over_messaging + 0.45*non_engagement))   // 0..100
 *
 * Bands: Critical >=80, High 60-79, Moderate 30-59, Low <30.
 * For a pure "how much have we burned" view, sort the CSV by total_campaigns desc, reply_rate asc.
 */
class WhatsAppFatigueReportGenerator
{
    private const RECENT_DAYS = 30;
    private const OVER_MESSAGING_CAP = 10;
    private const PROGRESS_INTERVAL = 1000;

    public function generate(WhatsAppReport $report): void
    {
        $relativePath = 'whatsapp/reports/whatsapp-fatigue-'.$report->id.'-'.now()->format('Ymd-His').'.csv';
        $absolutePath = storage_path('app/private/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        // window_from null = lifetime (no lower bound). Filter on scheduled_at — the actual send
        // time — NOT created_at, which is just when the row was imported (all "now" for a bulk load).
        $from = $report->window_from?->toDateTimeString();
        $windowClause = $from ? 'AND scheduled_at >= ?' : '';
        $bindings = $from ? [$from] : [];

        $handle = null;

        try {
            $total = (int) DB::scalar(
                "SELECT count(DISTINCT client_phone_number_id)
                 FROM whatsapp_messages
                 WHERE whatsapp_campaign_id IS NOT NULL {$windowClause}",
                $bindings
            );

            $report->update([
                'status' => WhatsAppReport::STATUS_PROCESSING,
                'file_name' => basename($relativePath),
                'storage_path' => $relativePath,
                'total_rows' => $total,
                'processed_rows' => 0,
                'error_message' => null,
                'started_at' => now(),
            ]);

            $handle = fopen($absolutePath, 'w');
            fputcsv($handle, [
                'phone', 'name', 'emirate', 'tier',
                'campaigns_last_30d', 'total_campaigns',
                'reply_rate', 'read_rate', 'last_messaged_at',
                'fatigue_score', 'fatigue_band',
            ]);

            $bands = ['Critical' => 0, 'High' => 0, 'Moderate' => 0, 'Low' => 0];
            $processed = 0;

            foreach (DB::cursor($this->sql($windowClause), $bindings) as $row) {
                $m = $this->score($row);
                $bands[$m['band']]++;

                fputcsv($handle, [
                    $row->normalized_phone,
                    $row->full_name,
                    $row->emirate,
                    $row->tier,
                    (int) $row->campaigns_last_30d,
                    (int) $row->total_campaigns,
                    $m['reply_rate'],
                    $m['read_rate'],
                    $row->last_messaged_at,
                    $m['fatigue'],
                    $m['band'],
                ]);

                $processed++;
                if ($processed % self::PROGRESS_INTERVAL === 0) {
                    $report->forceFill(['processed_rows' => $processed])->save();
                }
            }

            fclose($handle);
            $handle = null;

            $report->update([
                'status' => WhatsAppReport::STATUS_COMPLETED,
                'processed_rows' => $processed,
                'file_size' => Storage::disk('local')->size($relativePath),
                'completed_at' => now(),
                'summary' => [
                    'window_from' => $from,   // null = lifetime
                    'numbers' => $processed,
                    'bands' => $bands,
                ],
            ]);

            $this->notify($report, "Fatigue report ready — {$processed} numbers analysed.", true);
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }

            $report->update([
                'status' => WhatsAppReport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            \Sentry\captureException($e);
            $this->notify($report, 'Fatigue report failed: '.$e->getMessage(), false);

            throw $e;
        }
    }

    /**
     * @return array{fatigue: int, band: string, reply_rate: float, read_rate: float}
     */
    private function score(object $row): array
    {
        $recent  = (int) $row->campaigns_last_30d;
        $total   = (int) $row->total_campaigns;
        $replied = (int) $row->campaigns_replied;
        $read    = (int) $row->campaigns_read;

        $overMessaging = min($recent / self::OVER_MESSAGING_CAP, 1);
        $nonEngagement = $total > 0 ? ($total - $replied) / $total : 0.0;

        $fatigue = (int) round(100 * (0.55 * $overMessaging + 0.45 * $nonEngagement));

        return [
            'fatigue' => $fatigue,
            'band' => match (true) {
                $fatigue >= 80 => 'Critical',
                $fatigue >= 60 => 'High',
                $fatigue >= 30 => 'Moderate',
                default => 'Low',
            },
            'reply_rate' => $total > 0 ? round($replied / $total, 3) : 0.0,
            'read_rate' => $total > 0 ? round($read / $total, 3) : 0.0,
        ];
    }

    private function sql(string $windowClause): string
    {
        return "
            SELECT
                cpn.normalized_phone,
                c.full_name,
                c.emirate,
                c.tier,
                wpp.last_messaged_at,
                agg.campaigns_last_30d,
                agg.total_campaigns,
                agg.campaigns_replied,
                agg.campaigns_read
            FROM (
                SELECT
                    client_phone_number_id,
                    count(DISTINCT whatsapp_campaign_id) FILTER (WHERE scheduled_at >= now() - interval '".self::RECENT_DAYS." days') AS campaigns_last_30d,
                    count(DISTINCT whatsapp_campaign_id) AS total_campaigns,
                    count(DISTINCT whatsapp_campaign_id) FILTER (WHERE delivery_status = 'REPLIED') AS campaigns_replied,
                    count(DISTINCT whatsapp_campaign_id) FILTER (WHERE delivery_status IN ('READ','REPLIED')) AS campaigns_read
                FROM whatsapp_messages
                WHERE whatsapp_campaign_id IS NOT NULL {$windowClause}
                GROUP BY client_phone_number_id
            ) agg
            JOIN client_phone_numbers cpn ON cpn.id = agg.client_phone_number_id
            LEFT JOIN clients c ON c.id = cpn.client_id
            LEFT JOIN whatsapp_phone_profiles wpp ON wpp.client_phone_number_id = agg.client_phone_number_id
        ";
    }

    private function notify(WhatsAppReport $report, string $body, bool $success): void
    {
        $recipient = $report->requester;

        if (! $recipient) {
            return;
        }

        $notification = Notification::make()->title('WhatsApp Fatigue Report')->body($body);

        ($success ? $notification->success() : $notification->danger())->sendToDatabase($recipient);
    }
}
