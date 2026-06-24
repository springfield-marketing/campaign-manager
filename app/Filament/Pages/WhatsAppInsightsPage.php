<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsToWhatsApp;
use App\Modules\WhatsApp\Jobs\GenerateWhatsAppReport;
use App\Modules\WhatsApp\Models\WhatsAppReport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class WhatsAppInsightsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RestrictsToWhatsApp;

    protected string $view = 'filament.pages.whatsapp-insights';

    public static function getNavigationIcon(): string { return 'heroicon-o-chart-bar'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 90; }
    public static function getNavigationLabel(): string { return 'Insights'; }
    public function getTitle(): string { return 'WhatsApp Insights'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-insights'; }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_fatigue')
                ->label('Generate Fatigue Report')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Generate Contact-Fatigue report?')
                ->modalDescription('Runs in the background over the last 60 days of WhatsApp activity. You can leave this page — a download appears here and you get a notification when it is ready.')
                ->action(function (): void {
                    $running = WhatsAppReport::query()
                        ->whereIn('status', [WhatsAppReport::STATUS_PENDING, WhatsAppReport::STATUS_PROCESSING])
                        ->exists();

                    if ($running) {
                        Notification::make()->title('A report is already generating — wait for it to finish.')->warning()->send();

                        return;
                    }

                    $report = WhatsAppReport::create([
                        'type' => WhatsAppReport::TYPE_FATIGUE,
                        'status' => WhatsAppReport::STATUS_PENDING,
                        'requested_by' => auth()->id(),
                    ]);

                    GenerateWhatsAppReport::dispatch($report->id)->onQueue('analysis');

                    Notification::make()->title('Fatigue report queued — you can leave this page; it will notify you when ready.')->success()->send();
                }),
        ];
    }

    protected function table(Table $table): Table
    {
        return $table
            ->query(WhatsAppReport::query())
            ->poll('4s')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->badge()
                    ->color(fn (WhatsAppReport $record): string => $record->status === WhatsAppReport::STATUS_PROCESSING ? 'info' : 'gray')
                    ->getStateUsing(fn (WhatsAppReport $record): string => match ($record->status) {
                        WhatsAppReport::STATUS_PENDING => 'Queued',
                        WhatsAppReport::STATUS_PROCESSING => (int) $record->total_rows > 0
                            ? number_format($record->processed_rows).' / '.number_format($record->total_rows).' ('.$record->progressPercent().'%)'
                            : 'Starting…',
                        WhatsAppReport::STATUS_COMPLETED => number_format($record->processed_rows).' numbers',
                        default => '—',
                    }),

                TextColumn::make('requester.name')->label('By')->placeholder('—'),
                TextColumn::make('created_at')->label('Requested')->dateTime('d M H:i')->sortable(),
                TextColumn::make('completed_at')->label('Finished')->dateTime('d M H:i')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (WhatsAppReport $record): bool => $record->status === WhatsAppReport::STATUS_COMPLETED
                        && $record->storage_path
                        && Storage::disk('local')->exists($record->storage_path))
                    ->action(fn (WhatsAppReport $record) => Storage::disk('local')->download($record->storage_path, $record->file_name)),

                Action::make('error')
                    ->label('Error')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (WhatsAppReport $record): bool => $record->status === WhatsAppReport::STATUS_FAILED && filled($record->error_message))
                    ->modalHeading('Report failed')
                    ->modalContent(fn (WhatsAppReport $record) => new HtmlString('<pre class="text-sm whitespace-pre-wrap">'.e($record->error_message).'</pre>'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}
