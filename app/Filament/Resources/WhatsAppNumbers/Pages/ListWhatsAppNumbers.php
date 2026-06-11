<?php

namespace App\Filament\Resources\WhatsAppNumbers\Pages;

use App\Filament\Resources\WhatsAppNumbers\WhatsAppNumberResource;
use App\Filament\Widgets\WhatsAppNumberStatsWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListWhatsAppNumbers extends ListRecords
{
    protected static string $resource = WhatsAppNumberResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            WhatsAppNumberStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Filtered CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    TextInput::make('limit')
                        ->label('Number of records to export')
                        ->placeholder('Leave empty to export all')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Exports are randomised when a limit is set so you get a varied sample.'),
                ])
                ->action(function (array $data): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $limit = filled($data['limit'] ?? null) ? (int) $data['limit'] : null;
                    $query = WhatsAppNumberResource::getEloquentQuery();
                    $filters = $this->tableFilters ?? [];

                    $emirate = $filters['emirate']['value'] ?? null;
                    if (filled($emirate)) {
                        $query->whereExists(fn ($q) => $q
                            ->selectRaw('1')
                            ->from('clients')
                            ->whereColumn('clients.id', 'client_phone_numbers.client_id')
                            ->where('clients.emirate', $emirate)
                        );
                    }

                    $marketingAreaId = $filters['marketing_area']['value'] ?? null;
                    if (filled($marketingAreaId)) {
                        $query->whereExists(fn ($q) => $q
                            ->selectRaw('1')
                            ->from('ownerships')
                            ->whereColumn('ownerships.client_id', 'client_phone_numbers.client_id')
                            ->where('ownerships.marketing_area_id', $marketingAreaId)
                        );
                    }

                    $country = $filters['country']['value'] ?? null;
                    if (filled($country)) {
                        $query->where('is_uae', false)->where('detected_country', $country);
                    }

                    $campaignHistory = $filters['campaign_history']['value'] ?? null;
                    if ($campaignHistory === 'messaged') {
                        $query->whereHas('whatsAppMessages');
                    } elseif ($campaignHistory === 'new') {
                        $query->whereDoesntHave('whatsAppMessages');
                    }

                    $waStatus = $filters['wa_status']['value'] ?? null;
                    if ($waStatus === 'never_messaged') {
                        $query->whereDoesntHave('whatsAppProfile');
                    } elseif (in_array($waStatus, ['active', 'cooldown', 'dead'], true)) {
                        $query->whereHas('whatsAppProfile', fn ($q) => $q->where('usage_status', $waStatus));
                    }

                    $lastMessageStatus = $filters['last_message_status']['value'] ?? null;
                    if (filled($lastMessageStatus)) {
                        $query->whereHas('whatsAppProfile', fn ($p) =>
                            $p->whereRaw('upper(last_message_status) = ?', [strtoupper($lastMessageStatus)])
                        );
                    }

                    $query = self::activeExportQuery($query);

                    if ($limit) {
                        $query = DB::query()
                            ->fromSub($query->reorder(), 'filtered')
                            ->select('normalized_phone')
                            ->inRandomOrder()
                            ->limit($limit);
                    }

                    $limitSuffix = $limit ? "_limit{$limit}" : '';
                    $fileName = 'whatsapp_numbers_' . now()->format('Y-m-d') . $limitSuffix . '.csv';

                    return response()->streamDownload(function () use ($query): void {
                        set_time_limit(0);

                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['phone_number']);

                        foreach ($query->cursor() as $number) {
                            fputcsv($handle, [$number->normalized_phone]);
                        }

                        fclose($handle);
                    }, $fileName, ['Content-Type' => 'text/csv']);
                })
                ->modalHeading('Export WhatsApp Numbers')
                ->modalDescription('Exports eligible (active or never messaged, not suppressed) numbers matching the current filters. Optionally cap the export to a specific count — a random sample will be taken.'),
        ];
    }

    private static function activeExportQuery(Builder $query): Builder
    {
        return $query
            // Exclude suppressed numbers
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('contact_suppressions')
                ->whereColumn('contact_suppressions.client_phone_number_id', 'client_phone_numbers.id')
                ->where('contact_suppressions.channel', 'whatsapp')
                ->whereNull('contact_suppressions.released_at')
            )
            // Active or never messaged (no profile = never messaged = eligible)
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('whatsAppProfile')
                ->orWhereHas('whatsAppProfile', fn (Builder $p) =>
                    $p->where('usage_status', 'active')
                )
            )
            ->select('client_phone_numbers.normalized_phone')
            ->distinct()
            ->reorder('client_phone_numbers.normalized_phone');
    }
}
