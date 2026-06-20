<?php

namespace App\Filament\Pages;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Filament\Pages\Page;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class WhatsAppFailureAnalysisPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.whatsapp-failure-analysis';

    public static function getNavigationIcon(): string { return 'heroicon-o-exclamation-triangle'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 86; }
    public static function getNavigationLabel(): string { return 'Failure Analysis'; }
    public function getTitle(): string { return 'WhatsApp Failure Analysis'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-failure-analysis'; }

    protected ?int $totalFailures = null;

    protected function table(Table $table): Table
    {
        // One row per distinct failure reason among FAILED messages, with how many messages and
        // how many distinct numbers it hit. Wrapped in a subquery so MIN(id) becomes a real `id`
        // column — otherwise Filament's primary-key pagination tiebreaker breaks the GROUP BY.
        $aggregate = WhatsAppMessage::query()
            ->where('delivery_status', 'FAILED')
            ->whereNotNull('failure_reason')
            ->where('failure_reason', '<>', '')
            ->groupBy('failure_reason')
            ->selectRaw('MIN(id) as id')
            ->selectRaw('failure_reason')
            ->selectRaw('count(*) as failures')
            ->selectRaw('count(distinct client_phone_number_id) as numbers');

        return $table
            ->query(WhatsAppMessage::query()->fromSub($aggregate, 'whatsapp_messages'))
            ->columns([
                TextColumn::make('failure_reason')
                    ->label('Failure reason')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('failures')
                    ->label('Failures')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('share')
                    ->label('Share of failures')
                    ->getStateUsing(function (WhatsAppMessage $record): string {
                        $total = $this->totalFailures();

                        return $total > 0
                            ? number_format((int) $record->failures / $total * 100, 1).'%'
                            : '—';
                    }),

                TextColumn::make('numbers')
                    ->label('Numbers affected')
                    ->numeric()
                    ->sortable()
                    ->color('danger'),
            ])
            ->defaultSort('failures', 'desc')
            ->recordAction(null)
            ->recordUrl(null)
            ->paginated([25, 50, 100]);
    }

    private function totalFailures(): int
    {
        return $this->totalFailures ??= (int) WhatsAppMessage::query()
            ->where('delivery_status', 'FAILED')
            ->whereNotNull('failure_reason')
            ->where('failure_reason', '<>', '')
            ->count();
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}
