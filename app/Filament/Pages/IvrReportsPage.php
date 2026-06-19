<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;

class IvrReportsPage extends Page implements HasForms
{
    use InteractsWithForms;

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
    }

    public function isCurrentMonth(): bool
    {
        return $this->month !== null
            && $this->year === now()->year
            && $this->month === now()->month;
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}
