<?php

namespace App\Filament\Resources\IvrNumbers\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\IvrNumbers\IvrNumberResource;
use App\Models\ClientPhoneNumber;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIvrNumber extends EditRecord
{
    protected static string $resource = IvrNumberResource::class;

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
