<?php

namespace App\Filament\Pages;

use App\Modules\IVR\Models\IvrScript;
use Filament\Pages\Page;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class IvrScriptPerformancePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.ivr-script-performance';

    public static function getNavigationIcon(): string { return 'heroicon-o-presentation-chart-line'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 85; }
    public static function getNavigationLabel(): string { return 'Script Performance'; }
    public function getTitle(): string { return 'IVR Script Performance'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'ivr-script-performance'; }

    protected function table(Table $table): Table
    {
        return $table
            ->query(
                // One row per script: answer/interest/lead aggregates across every campaign that
                // used it, from the call records. Answered/Interested/Lead show count + rate.
                IvrScript::query()
                    ->join('ivr_campaigns as c', 'c.ivr_script_id', '=', 'ivr_scripts.id')
                    ->join('ivr_call_records as r', 'r.ivr_campaign_id', '=', 'c.id')
                    ->groupBy('ivr_scripts.id', 'ivr_scripts.name')
                    ->select('ivr_scripts.id', 'ivr_scripts.name')
                    ->selectRaw('count(distinct c.id) as campaigns')
                    ->selectRaw('count(*) as total_calls')
                    ->selectRaw("sum(case when r.call_status = 'Answered' then 1 else 0 end) as answered")
                    ->selectRaw("sum(case when r.dtmf_outcome = 'interested' then 1 else 0 end) as interested")
                    ->selectRaw("sum(case when r.dtmf_outcome = 'more_info' then 1 else 0 end) as more_info")
                    ->selectRaw("sum(case when r.dtmf_outcome = 'unsubscribe' then 1 else 0 end) as unsubscribed")
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Script')
                    ->wrap(),

                TextColumn::make('campaigns')
                    ->label('Campaigns')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_calls')
                    ->label('Calls')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('answered')
                    ->label('Answered')
                    ->getStateUsing(fn (IvrScript $record): string =>
                        self::formatWithRate($record->answered, $record->total_calls))
                    ->color('success'),

                TextColumn::make('interested')
                    ->label('Interested')
                    ->getStateUsing(fn (IvrScript $record): string =>
                        self::formatWithRate($record->interested, $record->answered))
                    ->color('primary'),

                TextColumn::make('more_info')
                    ->label('More Info')
                    ->getStateUsing(fn (IvrScript $record): string =>
                        self::formatWithRate($record->more_info, $record->answered)),

                TextColumn::make('unsubscribed')
                    ->label('Unsubs')
                    ->numeric()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'warning' : 'gray'),

                TextColumn::make('lead')
                    ->label('Lead')
                    ->getStateUsing(fn (IvrScript $record): string =>
                        self::formatWithRate(($record->interested + $record->more_info), $record->answered))
                    ->color('success'),
            ])
            ->defaultSort('total_calls', 'desc')
            ->recordAction(null)
            ->recordUrl(null)
            ->paginated([20, 50, 100]);
    }

    /**
     * "{count} ({rate}% of base)" — matches the WhatsApp reports table presentation.
     */
    private static function formatWithRate(int|null $count, int|null $base): string
    {
        $count = (int) $count;
        $base  = (int) $base;

        if ($base === 0) {
            return number_format($count);
        }

        return number_format($count) . ' (' . number_format($count / $base * 100, 1) . '%)';
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}
