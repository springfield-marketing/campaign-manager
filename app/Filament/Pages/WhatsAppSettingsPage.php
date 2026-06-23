<?php

namespace App\Filament\Pages;

use App\Modules\WhatsApp\Jobs\BatchAnalyseWhatsAppNumbers;
use App\Modules\WhatsApp\Models\WhatsAppSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsAppSettingsPage extends Page implements HasForms
{
    use \App\Filament\Concerns\RestrictsToWhatsApp;

    use InteractsWithForms;

    protected string $view = 'filament.pages.whatsapp-settings';

    public static function getNavigationIcon(): string { return 'heroicon-o-cog-6-tooth'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 90; }
    public static function getNavigationLabel(): string { return 'Settings'; }
    public function getTitle(): string { return 'WhatsApp Settings'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-settings'; }

    public int $hard_fail_threshold;
    public int $bulk_dead_threshold;
    public int $no_engagement_threshold;
    public int $cooldown_no_engagement_days;
    public int $min_days_between_sends;
    public int $cooldown_quality_hold_days;
    public int $cooldown_experiment_days;
    public int $cooldown_regional_days;

    public function mount(): void
    {
        $s = WhatsAppSettings::current();

        $this->hard_fail_threshold        = $s->hard_fail_threshold;
        $this->bulk_dead_threshold        = $s->bulk_dead_threshold;
        $this->no_engagement_threshold    = $s->no_engagement_threshold;
        $this->cooldown_no_engagement_days = $s->cooldown_no_engagement_days;
        $this->min_days_between_sends     = $s->min_days_between_sends;
        $this->cooldown_quality_hold_days = $s->cooldown_quality_hold_days;
        $this->cooldown_experiment_days   = $s->cooldown_experiment_days;
        $this->cooldown_regional_days     = $s->cooldown_regional_days;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Number Health — Dead Detection')
                ->description('Controls when a number is permanently marked as dead and removed from all future exports.')
                ->columns(2)
                ->schema([
                    TextInput::make('hard_fail_threshold')
                        ->label('Consecutive Hard Fails to Mark Dead')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(50)
                        ->extraAttributes(['x-tooltip.raw' =>
                            'If a number fails with a hard error (invalid number, account deleted, permanently blocked) this many times in a row, it is marked dead and excluded from all future exports. Lower values are more aggressive — set higher if your list has intermittent delivery issues.'
                        ]),

                    TextInput::make('bulk_dead_threshold')
                        ->label('Bulk Dead — Total Message Threshold')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(100)
                        ->extraAttributes(['x-tooltip.raw' =>
                            'A secondary dead-detection rule: if a number has received this many total messages and every single non-system failure was a hard fail, it is marked dead. This catches numbers that occasionally deliver via system retries but are effectively unreachable. Works alongside the consecutive threshold.'
                        ]),
                ]),

            Section::make('Minimum Gap Between Sends')
                ->description('Prevents the same number from being exported to a new campaign too soon after the last one, regardless of delivery outcome.')
                ->columns(1)
                ->schema([
                    TextInput::make('min_days_between_sends')
                        ->label('Minimum Days Between Sends (0 = disabled)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(365)
                        ->extraAttributes(['x-tooltip.raw' =>
                            'After a message is sent to a number, it will be held in cooldown for this many days before it can be exported again. This applies even if the message was delivered successfully. Set to 0 to disable — numbers will be immediately eligible after a send. Recommended: 7–30 days depending on campaign frequency.'
                        ]),
                ]),

            Section::make('Engagement-Based Cooldown')
                ->description('Numbers that have not read their recent messages are cooled down automatically to protect sender reputation.')
                ->columns(2)
                ->schema([
                    TextInput::make('no_engagement_threshold')
                        ->label('Consecutive Unread Messages Before Cooldown')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(50)
                        ->extraAttributes(['x-tooltip.raw' =>
                            'If a number\'s most recent messages were delivered but never read (no read receipt, no reply, no click) this many times in a row, it enters cooldown. The streak is consecutive — a single read, reply, or click resets it to zero. Undelivered messages (failures) do not count. This protects your sender reputation: repeatedly messaging people who never open your messages increases spam risk.'
                        ]),

                    TextInput::make('cooldown_no_engagement_days')
                        ->label('No-Engagement Cooldown Duration (days)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(365)
                        ->extraAttributes(['x-tooltip.raw' =>
                            'How long a no-engagement number is held back before it becomes eligible again. After this period the number returns to active; if its next messages still go unread, the cooldown is applied again.'
                        ]),
                ]),

            Section::make('Failure-Based Cooldowns')
                ->description('WhatsApp sometimes returns specific failure reasons that indicate a temporary hold rather than a permanent problem. These control how long each type is held back.')
                ->columns(3)
                ->schema([
                    TextInput::make('cooldown_quality_hold_days')
                        ->label('Quality Hold (days)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(90)
                        ->extraAttributes(['x-tooltip.raw' =>
                            'Applied when WhatsApp returns "retry again in a few days" — this means your message quality rating is temporarily low. The number is eligible again after this many days. WhatsApp typically resolves quality holds within 1–7 days; setting this too low risks re-triggering the hold.'
                        ]),

                    TextInput::make('cooldown_experiment_days')
                        ->label('Experiment Hold (days)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(90)
                        ->extraAttributes(['x-tooltip.raw' =>
                            'Applied when WhatsApp returns "part of an experiment" — Meta sometimes limits delivery to certain numbers as part of internal A/B tests. This is temporary and outside your control. The number is held back for this many days.'
                        ]),

                    TextInput::make('cooldown_regional_days')
                        ->label('Regional Restriction (days)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(365)
                        ->extraAttributes(['x-tooltip.raw' =>
                            'Applied when WhatsApp returns a regional restriction error (e.g. "US recipients"). The number is ineligible for the set period. This is typically a permanent or long-term restriction — the default of 30 days is conservative; you may want to increase it.'
                        ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        WhatsAppSettings::current()->update([
            'hard_fail_threshold'        => (int) $data['hard_fail_threshold'],
            'bulk_dead_threshold'        => (int) $data['bulk_dead_threshold'],
            'no_engagement_threshold'    => (int) $data['no_engagement_threshold'],
            'cooldown_no_engagement_days' => (int) $data['cooldown_no_engagement_days'],
            'min_days_between_sends'     => (int) $data['min_days_between_sends'],
            'cooldown_quality_hold_days' => (int) $data['cooldown_quality_hold_days'],
            'cooldown_experiment_days'   => (int) $data['cooldown_experiment_days'],
            'cooldown_regional_days'     => (int) $data['cooldown_regional_days'],
        ]);

        Notification::make()->title('WhatsApp settings saved.')->success()->send();
    }

    public function reanalyse(): void
    {
        WhatsAppSettings::where('lock_key', 'default')->updateOrInsert(
            ['lock_key' => 'default'],
            [
                'reanalysis_status'       => 'pending',
                'reanalysis_started_at'   => now(),
                'reanalysis_completed_at' => null,
            ]
        );

        BatchAnalyseWhatsAppNumbers::dispatch([], trackProgress: true)->onQueue('analysis');

        Notification::make()
            ->title('Reanalysis queued.')
            ->body('Watch the status panel below — it updates every 3 seconds.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->icon('heroicon-o-check')
                ->color('primary'),

            Action::make('reanalyse')
                ->label('Reanalyse All Numbers')
                ->action('reanalyse')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reanalyse all WhatsApp numbers?')
                ->modalDescription('This rebuilds every number\'s usage_status and cooldown_until based on the current settings. Run this after changing any setting on this page — otherwise the changes only apply to numbers processed in future imports. The job runs in the background and may take a few minutes on large datasets.'),
        ];
    }
}
