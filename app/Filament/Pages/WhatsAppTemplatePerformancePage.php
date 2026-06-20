<?php

namespace App\Filament\Pages;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Filament\Pages\Page;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class WhatsAppTemplatePerformancePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.whatsapp-template-performance';

    public static function getNavigationIcon(): string { return 'heroicon-o-presentation-chart-line'; }
    public static function getNavigationGroup(): ?string { return 'WhatsApp'; }
    public static function getNavigationSort(): ?int { return 85; }
    public static function getNavigationLabel(): string { return 'Template Performance'; }
    public function getTitle(): string { return 'WhatsApp Template Performance'; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'whatsapp-template-performance'; }

    protected function table(Table $table): Table
    {
        // One row per template across every message that used it. Delivered/Failed are of all
        // messages; Read is of delivered; Replied is of all messages. The aggregation is wrapped
        // in a subquery so MIN(id) becomes a real `id` column — otherwise Filament's primary-key
        // pagination tiebreaker (order by whatsapp_messages.id) breaks the GROUP BY.
        $aggregate = WhatsAppMessage::query()
            ->whereNotNull('template_name')
            ->where('template_name', '<>', '')
            ->groupBy('template_name')
            ->selectRaw('MIN(id) as id')
            ->selectRaw('template_name')
            ->selectRaw('count(*) as messages')
            ->selectRaw("sum(case when delivery_status = 'DELIVERED' then 1 else 0 end) as delivered")
            ->selectRaw("sum(case when delivery_status = 'READ' then 1 else 0 end) as read")
            ->selectRaw("sum(case when delivery_status = 'REPLIED' then 1 else 0 end) as replied")
            ->selectRaw("sum(case when delivery_status = 'FAILED' then 1 else 0 end) as failed");

        return $table
            ->query(WhatsAppMessage::query()->fromSub($aggregate, 'whatsapp_messages'))
            ->columns([
                TextColumn::make('template_name')
                    ->label('Template')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('messages')
                    ->label('Messages')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('delivered')
                    ->label('Delivered')
                    ->getStateUsing(fn (WhatsAppMessage $record): string =>
                        self::formatWithRate($record->delivered, $record->messages))
                    ->color('success'),

                TextColumn::make('read')
                    ->label('Read')
                    ->getStateUsing(fn (WhatsAppMessage $record): string =>
                        self::formatWithRate($record->read, $record->delivered))
                    ->color('primary'),

                TextColumn::make('replied')
                    ->label('Replied')
                    ->getStateUsing(fn (WhatsAppMessage $record): string =>
                        self::formatWithRate($record->replied, $record->messages))
                    ->color('success'),

                TextColumn::make('failed')
                    ->label('Failed')
                    ->getStateUsing(fn (WhatsAppMessage $record): string =>
                        self::formatWithRate($record->failed, $record->messages))
                    ->color(fn (WhatsAppMessage $record): string => $record->failed > 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('messages', 'desc')
            ->recordAction(null)
            ->recordUrl(null)
            ->paginated([25, 50, 100]);
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
