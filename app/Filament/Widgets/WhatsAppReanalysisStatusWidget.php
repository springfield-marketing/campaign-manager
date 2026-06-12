<?php

namespace App\Filament\Widgets;

use App\Modules\WhatsApp\Models\WhatsAppSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WhatsAppReanalysisStatusWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '3s';

    public function getHeading(): ?string
    {
        return 'Number Profile Reanalysis';
    }

    protected function getStats(): array
    {
        $s      = WhatsAppSettings::current();
        $status = $s->reanalysis_status;

        if ($status === null) {
            return [
                Stat::make('Status', 'Never run')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->description('Click "Reanalyse All Numbers" to rebuild profiles against the current settings'),
            ];
        }

        $statusStat = Stat::make('Status', match ($status) {
            'pending'   => 'Queued',
            'running'   => 'Running…',
            'completed' => 'Completed',
            'failed'    => 'Failed',
            default     => ucfirst($status),
        })
            ->icon(match ($status) {
                'pending'   => 'heroicon-o-clock',
                'running'   => 'heroicon-o-arrow-path',
                'completed' => 'heroicon-o-check-circle',
                'failed'    => 'heroicon-o-x-circle',
                default     => 'heroicon-o-clock',
            })
            ->color(match ($status) {
                'pending'   => 'gray',
                'running'   => 'warning',
                'completed' => 'success',
                'failed'    => 'danger',
                default     => 'gray',
            });

        $stats = [$statusStat];

        if ($status === 'pending' && $s->reanalysis_started_at) {
            $stats[] = Stat::make('Queued', $s->reanalysis_started_at->diffForHumans())
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->description('Waiting for a queue worker to pick this up');
        }

        if ($status === 'running' && $s->reanalysis_started_at) {
            $elapsed   = now()->diffInSeconds($s->reanalysis_started_at);
            $estimated = $s->last_run_duration_seconds;

            if ($estimated && $estimated > 0) {
                $pct       = min(99, (int) round($elapsed / $estimated * 100));
                $remaining = max(0, $estimated - $elapsed);
                $bar       = str_repeat('█', (int) round($pct / 10))
                           . str_repeat('░', 10 - (int) round($pct / 10));

                $stats[] = Stat::make('Progress', $bar . '  ' . $pct . '%')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->description(
                        $this->formatSeconds($elapsed) . ' elapsed'
                        . ' · ~' . $this->formatSeconds($remaining) . ' remaining'
                        . ' (based on last run: ' . $this->formatSeconds($estimated) . ')'
                    );
            } else {
                $stats[] = Stat::make('Running for', $this->formatSeconds($elapsed))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->description('No estimate — this is the first run');
            }
        }

        if ($status === 'completed' && $s->reanalysis_started_at && $s->reanalysis_completed_at) {
            $duration = $s->reanalysis_completed_at->diffInSeconds($s->reanalysis_started_at);
            $stats[] = Stat::make('Finished', $s->reanalysis_completed_at->diffForHumans())
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description('Took ' . $this->formatSeconds($duration));
        }

        if ($status === 'failed' && $s->reanalysis_completed_at) {
            $stats[] = Stat::make('Failed', $s->reanalysis_completed_at->diffForHumans())
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->description('Check the queue worker logs for the error');
        }

        return $stats;
    }

    private function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $mins = intdiv($seconds, 60);
        $secs = $seconds % 60;

        return $mins . 'm ' . $secs . 's';
    }
}
