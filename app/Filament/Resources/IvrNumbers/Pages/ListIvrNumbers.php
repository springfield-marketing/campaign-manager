<?php

namespace App\Filament\Resources\IvrNumbers\Pages;

use App\Filament\Resources\IvrNumbers\IvrNumberResource;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListIvrNumbers extends ListRecords
{
    protected static string $resource = IvrNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Select::make('limit')
                        ->label('Export limit')
                        ->options([
                            '500'  => '500 numbers',
                            '1000' => '1,000 numbers',
                            '2000' => '2,000 numbers',
                            '5000' => '5,000 numbers',
                            'all'  => 'All eligible numbers',
                        ])
                        ->default('1000')
                        ->required(),
                ])
                ->action(function (array $data): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $limit = $data['limit'] === 'all' ? null : (int) $data['limit'];

                    $query = ClientPhoneNumber::query()
                        ->where('is_uae', true)
                        ->whereHas('client', fn ($q) => $q->whereNotNull('full_name')->whereRaw("trim(full_name) <> ''"))
                        ->whereDoesntHave('suppressions', fn ($q) =>
                            $q->whereNull('released_at')->where(fn ($q) =>
                                $q->whereNull('channel')->orWhere('channel', 'ivr')
                            )
                        )
                        ->whereNull('unsubscribed_at')
                        ->with(['client.primaryEmail', 'ivrProfile'])
                        ->where(fn ($q) =>
                            $q->whereDoesntHave('ivrProfile')
                              ->orWhereHas('ivrProfile', fn ($p) =>
                                  $p->where('usage_status', 'active')
                                    ->where(fn ($p) =>
                                        $p->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', now())
                                    )
                              )
                        )
                        ->orderByDesc('is_primary')
                        ->orderBy('id');

                    if ($limit) {
                        $query->limit($limit);
                    }

                    $numbers = $query->get();

                    return response()->streamDownload(function () use ($numbers): void {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Phone', 'Name', 'Email', 'Emirate', 'IVR Status', 'Last Called', 'Cooldown Until']);

                        foreach ($numbers as $number) {
                            fputcsv($handle, [
                                $number->normalized_phone,
                                $number->client?->full_name,
                                $number->client?->primaryEmail?->email,
                                $number->client?->emirate,
                                $number->ivrProfile?->usage_status ?? 'active',
                                $number->ivrProfile?->last_called_at?->format('Y-m-d'),
                                $number->ivrProfile?->cooldown_until?->format('Y-m-d'),
                            ]);
                        }

                        fclose($handle);
                    }, 'ivr_numbers_export_' . now()->format('Y-m-d') . '.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                })
                ->modalHeading('Export IVR Numbers')
                ->modalDescription('Downloads active, eligible IVR numbers (not suppressed, not in cooldown).'),
        ];
    }
}
