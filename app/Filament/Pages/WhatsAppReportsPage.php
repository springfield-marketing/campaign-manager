<?php

namespace App\Filament\Pages;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Modules\WhatsApp\Models\WhatsAppCampaign;
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

class WhatsAppReportsPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected string $view = 'filament.pages.whatsapp-reports';

    public static function getNavigationIcon(): string { return 'heroicon-o-chart-bar'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 80; }
    public static function getNavigationLabel(): string { return 'Reports'; }
    public function getTitle(): string { return 'WhatsApp Reports'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-reports'; }

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

        $this->dispatch('whatsapp-reports-filter-changed', year: $this->year, month: $this->month);

        $this->resetTable();
    }

    protected function table(Table $table): Table
    {
        $year  = $this->year;
        $month = $this->month;

        return $table
            ->query(
                WhatsAppCampaign::query()
                    ->when($year,  fn ($q) => $q->whereYear('started_at', $year))
                    ->when($month, fn ($q) => $q->whereMonth('started_at', $month))
            )
            ->columns([
                TextColumn::make('started_at')
                    ->label('Date')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y') : '—')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Campaign')
                    ->searchable()
                    ->url(fn (WhatsAppCampaign $record): string =>
                        WhatsAppCampaignResource::getUrl('edit', ['record' => $record->getKey()])
                    ),

                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('total_messages')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('delivered')
                    ->label('Delivered')
                    ->getStateUsing(fn (WhatsAppCampaign $record): string =>
                        self::formatWithRate($record->delivered_count, $record->total_messages)
                    )
                    ->color('success'),

                TextColumn::make('read')
                    ->label('Read')
                    ->getStateUsing(fn (WhatsAppCampaign $record): string =>
                        self::formatWithRate($record->read_count, $record->delivered_count)
                    )
                    ->color('primary'),

                TextColumn::make('replied')
                    ->label('Replied')
                    ->getStateUsing(fn (WhatsAppCampaign $record): string =>
                        self::formatWithRate($record->replied_count, $record->total_messages)
                    )
                    ->color('success'),

                TextColumn::make('failed')
                    ->label('Failed')
                    ->getStateUsing(fn (WhatsAppCampaign $record): string =>
                        self::formatWithRate($record->failed_count, $record->total_messages)
                    )
                    ->color(fn (WhatsAppCampaign $record): string =>
                        ($record->failed_count ?? 0) > 0 ? 'danger' : 'gray'
                    ),

                TextColumn::make('unsubscribed_count')
                    ->label('Unsubs')
                    ->numeric()
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'warning' : 'gray'),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('started_at', 'desc')
            ->recordAction(null)
            ->recordUrl(null)
            ->paginated([20, 50, 100]);
    }

    private static function formatWithRate(int|null $count, int|null $base): string
    {
        $count = (int) $count;
        $base  = (int) $base;

        if ($base === 0) {
            return number_format($count);
        }

        $rate = number_format($count / $base * 100, 1);

        return number_format($count) . " ({$rate}%)";
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}
