<?php

namespace App\Filament\Pages;

use App\Modules\IVR\Support\IvrReportData;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IvrReportsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.ivr-reports';

    public static function getNavigationIcon(): string { return 'heroicon-o-chart-bar'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 80; }
    public static function getNavigationLabel(): string { return 'IVR Reports'; }
    public function getTitle(): string { return 'IVR Reports'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'ivr-reports'; }

    public int $year;
    public ?int $month;

    public function mount(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('year')
                    ->label('Year')
                    ->numeric()
                    ->required()
                    ->default(now()->year),

                Select::make('month')
                    ->label('Month')
                    ->options([
                        '' => 'Whole year',
                        1  => 'January',   2  => 'February', 3  => 'March',
                        4  => 'April',     5  => 'May',       6  => 'June',
                        7  => 'July',      8  => 'August',    9  => 'September',
                        10 => 'October',   11 => 'November', 12 => 'December',
                    ])
                    ->default(now()->month),
            ])->columns(3),
        ]);
    }

    public function apply(): void
    {
        $data = $this->form->getState();
        $this->year  = (int) $data['year'];
        $this->month = $data['month'] !== null && $data['month'] !== '' ? (int) $data['month'] : null;
    }

    public function getReportData(): array
    {
        return app(IvrReportData::class)->forPeriod($this->year, $this->month);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
