<?php

namespace App\Filament\Pages;

use App\Modules\IVR\Models\IvrCampaign;
use App\Modules\IVR\Models\IvrSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class IvrReportsPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.ivr-reports';

    public static function getNavigationIcon(): string { return 'heroicon-o-chart-bar'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 80; }
    public static function getNavigationLabel(): string { return 'Reports'; }
    public function getTitle(): string { return 'IVR Reports'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'ivr-reports'; }

    public int $year;
    public ?int $month = null;

    public function mount(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'sm' => 3])->components([
            TextInput::make('year')
                ->label('Year')
                ->numeric()
                ->required()
                ->default(now()->year)
                ->extraInputAttributes(['style' => 'width: 100px']),

            Select::make('month')
                ->label('Month')
                ->options([
                    ''  => 'Whole year',
                    1   => 'January',   2  => 'February', 3  => 'March',
                    4   => 'April',     5  => 'May',       6  => 'June',
                    7   => 'July',      8  => 'August',    9  => 'September',
                    10  => 'October',   11 => 'November', 12 => 'December',
                ])
                ->default(now()->month)
                ->selectablePlaceholder(false),

            SchemaActions::make([
                Action::make('apply')
                    ->label('Apply')
                    ->color('primary')
                    ->action('apply'),
            ])->verticallyAlignEnd(),
        ]);
    }

    public function apply(): void
    {
        $data = $this->form->getState();

        $this->year  = (int) $data['year'];
        $this->month = ($data['month'] !== null && $data['month'] !== '') ? (int) $data['month'] : null;

        $this->dispatch('ivr-filter-changed', year: $this->year, month: $this->month);

        $this->resetTable();
    }

    public function getHeaderWidgets(): array
    {
        return [];
    }

    public function isCurrentMonth(): bool
    {
        return $this->month !== null
            && $this->year === now()->year
            && $this->month === now()->month;
    }

    protected function table(Table $table): Table
    {
        $year  = $this->year;
        $month = $this->month;

        $driver = DB::connection()->getDriverName();
        $billableMinutes = $driver === 'sqlite'
            ? "coalesce(sum(case when call_status <> 'Answered' then 0 when total_duration_seconds <= 0 then 0 when total_duration_seconds <= 60 then 1 else cast((total_duration_seconds + 59) / 60 as integer) end), 0)"
            : "coalesce(sum(case when call_status <> 'Answered' then 0 when total_duration_seconds <= 0 then 0 when total_duration_seconds <= 60 then 1 else ceiling(total_duration_seconds / 60.0) end), 0)";

        $settings   = IvrSettings::current();
        $underRate  = (float) $settings->price_per_minute_under;
        $overRate   = (float) $settings->price_per_minute_over;
        $quota      = $settings->monthly_minutes_quota;
        $blendedRate = $underRate;

        return $table
            ->query(
                IvrCampaign::query()
                    ->join('ivr_call_records', 'ivr_call_records.ivr_campaign_id', '=', 'ivr_campaigns.id')
                    ->when($year,  fn ($q) => $q->whereYear('ivr_call_records.call_time', $year))
                    ->when($month, fn ($q) => $q->whereMonth('ivr_call_records.call_time', $month))
                    ->groupBy('ivr_campaigns.id', 'ivr_campaigns.external_campaign_id')
                    ->selectRaw('ivr_campaigns.id as id')
                    ->selectRaw('ivr_campaigns.external_campaign_id')
                    ->selectRaw('min(ivr_call_records.call_time) as campaign_started_at')
                    ->selectRaw('max(ivr_call_records.call_time) as campaign_completed_at')
                    ->selectRaw('count(*) as calls_count')
                    ->selectRaw("sum(case when ivr_call_records.dtmf_outcome = 'interested' then 1 else 0 end) as leads_count")
                    ->selectRaw("sum(case when ivr_call_records.dtmf_outcome = 'more_info' then 1 else 0 end) as more_info_count")
                    ->selectRaw("sum(case when ivr_call_records.call_status = 'Answered' then 1 else 0 end) as answered_calls")
                    ->selectRaw("sum(case when ivr_call_records.dtmf_outcome = 'unsubscribe' then 1 else 0 end) as unsubscribed_calls")
                    ->selectRaw("{$billableMinutes} as minutes_used")
                    ->selectRaw("({$billableMinutes}) * {$blendedRate} as campaign_cost")
                    ->orderByDesc('campaign_completed_at')
            )
            ->columns([
                TextColumn::make('campaign_started_at')
                    ->label('Date')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : '—')
                    ->sortable(),

                TextColumn::make('external_campaign_id')
                    ->label('Campaign ID')
                    ->searchable(),

                TextColumn::make('calls_count')
                    ->label('Calls')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('leads_total')
                    ->label('Leads')
                    ->getStateUsing(fn ($record) => (int) $record->leads_count + (int) $record->more_info_count)
                    ->numeric(),

                TextColumn::make('minutes_used')
                    ->label('Minutes')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('campaign_cost')
                    ->label('Cost (AED)')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->sortable(),

                TextColumn::make('cpl')
                    ->label('CPL (AED)')
                    ->getStateUsing(function ($record) use ($underRate, $overRate, $quota) {
                        $answered   = (int) $record->answered_calls;
                        $unsubs     = (int) $record->unsubscribed_calls;
                        $costGross  = (float) $record->campaign_cost;
                        $costAns    = $answered > 0 ? $costGross * max(0, $answered - $unsubs) / $answered : 0;
                        $leads      = (int) $record->leads_count + (int) $record->more_info_count;

                        return $leads > 0 ? number_format($costAns / $leads, 2) : '—';
                    }),
            ])
            ->defaultSort('campaign_started_at', 'desc')
            ->recordAction(null)
            ->recordUrl(null)
            ->paginated([20, 50, 100]);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}
