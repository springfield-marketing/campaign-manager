<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class IvrScriptPerformancePage extends Page
{
    protected string $view = 'filament.pages.ivr-script-performance';

    public static function getNavigationIcon(): string { return 'heroicon-o-presentation-chart-line'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 85; }
    public static function getNavigationLabel(): string { return 'Script Performance'; }
    public function getTitle(): string { return 'IVR Script Performance'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'ivr-script-performance'; }

    /**
     * Per-script effectiveness across every campaign that used the script. Answer/interest/lead
     * rates are computed from the call records (the source of truth), grouped by linked script.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return DB::table('ivr_call_records as r')
            ->join('ivr_campaigns as c', 'c.id', '=', 'r.ivr_campaign_id')
            ->join('ivr_scripts as s', 's.id', '=', 'c.ivr_script_id')
            ->groupBy('s.id', 's.name')
            ->selectRaw('s.name as name')
            ->selectRaw('count(distinct c.id) as campaigns')
            ->selectRaw('count(*) as total_calls')
            ->selectRaw("sum(case when r.call_status = 'Answered' then 1 else 0 end) as answered")
            ->selectRaw("sum(case when r.dtmf_outcome = 'interested' then 1 else 0 end) as interested")
            ->selectRaw("sum(case when r.dtmf_outcome = 'more_info' then 1 else 0 end) as more_info")
            ->selectRaw("sum(case when r.dtmf_outcome = 'unsubscribe' then 1 else 0 end) as unsubscribed")
            ->orderByDesc('total_calls')
            ->get()
            ->map(function ($r): array {
                $total = (int) $r->total_calls;
                $answered = (int) $r->answered;
                $interested = (int) $r->interested;
                $moreInfo = (int) $r->more_info;

                return [
                    'name' => $r->name,
                    'campaigns' => (int) $r->campaigns,
                    'total_calls' => $total,
                    'answered' => $answered,
                    'answer_rate' => $total > 0 ? round($answered / $total * 100, 1) : 0.0,
                    'interested' => $interested,
                    // Rates are of ANSWERED calls — "of people who picked up, how many engaged".
                    'interested_rate' => $answered > 0 ? round($interested / $answered * 100, 1) : 0.0,
                    'more_info' => $moreInfo,
                    'unsubscribed' => (int) $r->unsubscribed,
                    'lead_rate' => $answered > 0 ? round(($interested + $moreInfo) / $answered * 100, 1) : 0.0,
                ];
            })
            ->all();
    }
}
