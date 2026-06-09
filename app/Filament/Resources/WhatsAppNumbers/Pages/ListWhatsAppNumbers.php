<?php

namespace App\Filament\Resources\WhatsAppNumbers\Pages;

use App\Filament\Resources\WhatsAppNumbers\WhatsAppNumberResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWhatsAppNumbers extends ListRecords
{
    protected static string $resource = WhatsAppNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Filtered CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): \Symfony\Component\HttpFoundation\StreamedResponse {
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

                    $query = self::activeExportQuery($query);
                    $fileName = 'whatsapp_numbers_' . now()->format('Y-m-d') . '.csv';

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
                ->modalDescription('Exports eligible (active or never messaged, not suppressed) numbers matching the current location filters.'),
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
