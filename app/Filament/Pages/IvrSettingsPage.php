<?php

namespace App\Filament\Pages;

use App\Modules\IVR\Jobs\ExportCentralDatabase;
use App\Modules\IVR\Models\CentralDatabaseExport;
use App\Modules\IVR\Models\IvrSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class IvrSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.ivr-settings';

    public static function getNavigationIcon(): string { return 'heroicon-o-cog-6-tooth'; }
    public static function getNavigationGroup(): ?string { return 'IVR'; }
    public static function getNavigationSort(): ?int { return 90; }
    public static function getNavigationLabel(): string { return 'IVR Settings'; }
    public function getTitle(): string { return 'IVR Settings'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'ivr-settings'; }

    public int $monthly_minutes_quota = 50000;
    public string $price_per_minute_under = '0.3700';
    public string $price_per_minute_over = '0.4000';
    public int $cooldown_answered_days = 14;
    public int $cooldown_missed_days = 1;

    public function mount(): void
    {
        $settings = IvrSettings::current();

        $this->monthly_minutes_quota   = $settings->monthly_minutes_quota;
        $this->price_per_minute_under  = number_format((float) $settings->price_per_minute_under, 4, '.', '');
        $this->price_per_minute_over   = number_format((float) $settings->price_per_minute_over, 4, '.', '');
        $this->cooldown_answered_days  = $settings->cooldown_answered_days;
        $this->cooldown_missed_days    = $settings->cooldown_missed_days;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Monthly Quota & Pricing')
                ->description('Used to calculate costs on the Reports page. Check your contract or invoices for the correct rates.')
                ->columns(2)
                ->schema([
                    TextInput::make('monthly_minutes_quota')
                        ->label('Monthly Minutes Quota')
                        ->numeric()
                        ->required()
                        ->minValue(1),

                    TextInput::make('price_per_minute_under')
                        ->label('Price per Minute (Under Quota) — AED')
                        ->numeric()
                        ->required()
                        ->step('0.0001')
                        ->minValue(0)
                        ->helperText('Applied to minutes within the monthly quota.'),

                    TextInput::make('price_per_minute_over')
                        ->label('Price per Minute (Over Quota) — AED')
                        ->numeric()
                        ->required()
                        ->step('0.0001')
                        ->minValue(0)
                        ->helperText('Applied to minutes beyond the quota — usually higher.'),
                ]),

            Section::make('Cooldown Periods')
                ->description('How long a number is held back from the next campaign after a call.')
                ->columns(2)
                ->schema([
                    TextInput::make('cooldown_answered_days')
                        ->label('Answered Call Cooldown (days)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(365),

                    TextInput::make('cooldown_missed_days')
                        ->label('Missed Call Cooldown (days)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(365),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        IvrSettings::current()->update([
            'monthly_minutes_quota'  => (int) $data['monthly_minutes_quota'],
            'price_per_minute_under' => $data['price_per_minute_under'],
            'price_per_minute_over'  => $data['price_per_minute_over'],
            'cooldown_answered_days' => (int) $data['cooldown_answered_days'],
            'cooldown_missed_days'   => (int) $data['cooldown_missed_days'],
        ]);

        Notification::make()->title('Settings saved.')->success()->send();
    }

    public function startExport(): void
    {
        $running = CentralDatabaseExport::query()
            ->whereIn('status', [CentralDatabaseExport::STATUS_PENDING, CentralDatabaseExport::STATUS_PROCESSING])
            ->exists();

        if ($running) {
            Notification::make()->title('An export is already running.')->warning()->send();
            return;
        }

        $export = CentralDatabaseExport::create([
            'status'       => CentralDatabaseExport::STATUS_PENDING,
            'requested_by' => auth()->id(),
        ]);

        ExportCentralDatabase::dispatch($export->id);

        Notification::make()->title('Database export queued — check back in a few minutes.')->success()->send();
    }

    public function downloadExport(int $exportId): void
    {
        $export = CentralDatabaseExport::findOrFail($exportId);

        if ($export->status !== CentralDatabaseExport::STATUS_COMPLETED) return;
        if (! $export->storage_path || ! Storage::disk('local')->exists($export->storage_path)) return;

        $this->redirect(route('ivr.db-export.download', $export));
    }

    public function getDatabaseExports(): \Illuminate\Database\Eloquent\Collection
    {
        return CentralDatabaseExport::query()
            ->with('requester')
            ->latest()
            ->limit(10)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->icon('heroicon-o-check')
                ->color('primary'),

            Action::make('start_export')
                ->label('Start Excel Export')
                ->action('startExport')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Start database export?')
                ->modalDescription('This will create a full Excel workbook of the business database. It may take several minutes.'),
        ];
    }
}
