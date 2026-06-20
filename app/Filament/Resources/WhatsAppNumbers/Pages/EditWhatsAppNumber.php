<?php

namespace App\Filament\Resources\WhatsAppNumbers\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\WhatsAppNumbers\WhatsAppNumberResource;
use App\Models\ClientPhoneNumber;
use App\Modules\WhatsApp\Support\WhatsAppBatchProfileUpdater;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppNumber extends EditRecord
{
    protected static string $resource = WhatsAppNumberResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $record = $this->getRecord();
        return $record->client?->full_name ?? $record->normalized_phone;
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $record = $this->getRecord();
        return $record->client?->full_name ? $record->normalized_phone : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Recompute this one number's WhatsApp health (status/cooldown) without waiting for
            // a global reanalysis.
            Action::make('reanalyse')
                ->label('Re-analyse')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    app(WhatsAppBatchProfileUpdater::class)->run([$this->getRecord()->id]);
                    $this->getRecord()->load('whatsAppProfile');

                    Notification::make()->title('Number re-analysed')->success()->send();
                }),

            // Manual override to clear a cooldown for an urgent send. Note: a later reanalysis
            // recomputes cooldown from the last-messaged date, so this is a temporary override.
            Action::make('clear_cooldown')
                ->label('Clear cooldown')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->visible(fn (): bool => $this->getRecord()->whatsAppProfile?->usage_status === 'cooldown')
                ->requiresConfirmation()
                ->modalHeading('Clear cooldown for an urgent send?')
                ->modalDescription('Marks this number active now. A later re-analysis will recompute cooldown from its last-messaged date.')
                ->action(function (): void {
                    $this->getRecord()->whatsAppProfile?->forceFill([
                        'usage_status'   => 'active',
                        'cooldown_until' => null,
                    ])->save();
                    $this->getRecord()->load('whatsAppProfile');

                    Notification::make()->title('Cooldown cleared')->success()->send();
                }),

            Action::make('view_contact')
                ->label('View Full Contact')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => ClientResource::getUrl('edit', [
                    'record' => $this->getRecord()->client_id,
                ]))
                ->visible(fn (): bool => $this->getRecord()->client_id !== null),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ClientPhoneNumber $record */
        $record = $this->getRecord();
        $client = $record->client;

        $data['client_full_name']   = $client?->full_name;
        $data['client_email']       = $client?->primaryEmail?->email;
        $data['client_emirate']     = $client?->emirate;
        $data['client_nationality'] = $client?->nationality;
        $data['client_gender']      = $client?->gender;
        $data['client_interest']    = $client?->interest;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var ClientPhoneNumber $record */
        $record = $this->getRecord();
        $client = $record->client;

        if ($client) {
            $client->update([
                'full_name'   => $data['client_full_name'] ?? $client->full_name,
                'emirate'     => $data['client_emirate'] ?? $client->emirate,
                'nationality' => $data['client_nationality'] ?? $client->nationality,
                'gender'      => $data['client_gender'] ?? $client->gender,
                'interest'    => $data['client_interest'] ?? $client->interest,
            ]);

            if (! empty($data['client_email'])) {
                $client->setPrimaryEmailAddress($data['client_email']);
            }
        }

        unset(
            $data['client_full_name'],
            $data['client_email'],
            $data['client_emirate'],
            $data['client_nationality'],
            $data['client_gender'],
            $data['client_interest'],
        );

        return $data;
    }
}
