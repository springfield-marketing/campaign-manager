<?php

namespace App\Filament\Resources\WhatsAppNumbers\Tables;

use App\Filament\Filters\PhoneSearchFilter;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\MarketingArea;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class WhatsAppNumbersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('normalized_phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('client.full_name')
                    ->label('Contact')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('client.primaryEmail.email')
                    ->label('Email')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('client.emirate')
                    ->label('Emirate')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('detected_country')
                    ->label('Country')
                    ->getStateUsing(fn (ClientPhoneNumber $record): string =>
                        $record->is_uae ? 'UAE' : ($record->detected_country ?? '—')
                    )
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                TextColumn::make('last_source_name')
                    ->label('Source')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('wa_status')
                    ->label('WA Status')
                    ->badge()
                    ->getStateUsing(fn (ClientPhoneNumber $record): string =>
                        (bool) $record->is_whatsapp_suppressed
                            ? 'unsubscribed'
                            : ($record->whatsAppProfile?->usage_status ?? 'never_messaged')
                    )
                    ->color(fn (string $state): string => match ($state) {
                        'active'         => 'success',
                        'cooldown'       => 'warning',
                        'dead'           => 'danger',
                        'unsubscribed'   => 'danger',
                        'never_messaged' => 'gray',
                        default          => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active'         => 'Active',
                        'cooldown'       => 'Cooldown',
                        'dead'           => 'Dead',
                        'unsubscribed'   => 'Unsubscribed',
                        'never_messaged' => 'Never Messaged',
                        default          => ucfirst($state),
                    }),

                TextColumn::make('whatsAppProfile.cooldown_until')
                    ->label('Cooldown Until')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('whatsAppProfile.last_messaged_at')
                    ->label('Last Messaged')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('whats_app_messages_count')
                    ->label('Messages')
                    ->counts('whatsAppMessages')
                    ->sortable(),

                TextColumn::make('whatsAppProfile.last_message_status')
                    ->label('Last Status')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'DELIVERED', 'READ', 'REPLIED' => 'success',
                        'SENT'                         => 'info',
                        'FAILED'                       => 'danger',
                        default                        => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst(strtolower((string) $state)))
                    ->placeholder('—'),

                IconColumn::make('is_whatsapp_suppressed')
                    ->label('Suppressed')
                    ->boolean(),
            ])
            ->filters([
                PhoneSearchFilter::make('phone', fn (Builder $query, array $candidates) =>
                    $query->where(function (Builder $q) use ($candidates): void {
                        foreach ($candidates as $candidate) {
                            $q->orWhere('client_phone_numbers.normalized_phone', 'like', '%'.$candidate.'%')
                              ->orWhere('client_phone_numbers.raw_phone', 'like', '%'.$candidate.'%');
                        }
                    })
                ),

                SelectFilter::make('wa_status')
                    ->label('WA Status')
                    ->options([
                        'never_messaged' => 'Never Messaged',
                        'active'         => 'Active',
                        'cooldown'       => 'Cooldown',
                        'dead'           => 'Dead',
                        'unsubscribed'   => 'Unsubscribed',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'never_messaged' => $query->whereDoesntHave('whatsAppProfile'),
                        'active'         => $query->whereHas('whatsAppProfile', fn ($q) => $q->where('usage_status', 'active')),
                        'cooldown'       => $query->whereHas('whatsAppProfile', fn ($q) => $q->where('usage_status', 'cooldown')),
                        'dead'           => $query->whereHas('whatsAppProfile', fn ($q) => $q->where('usage_status', 'dead')),
                        'unsubscribed'   => $query->whereExists(fn ($q) => $q
                            ->selectRaw('1')
                            ->from('contact_suppressions')
                            ->whereColumn('contact_suppressions.client_phone_number_id', 'client_phone_numbers.id')
                            ->where('contact_suppressions.channel', 'whatsapp')
                            ->whereNull('contact_suppressions.released_at')
                        ),
                        default => $query,
                    }),

                SelectFilter::make('emirate')
                    ->label('Emirate')
                    ->options(fn () => ClientPhoneNumber::query()
                        ->join('clients', 'clients.id', '=', 'client_phone_numbers.client_id')
                        ->whereNotNull('clients.emirate')
                        ->whereRaw("trim(clients.emirate) <> ''")
                        ->distinct()
                        ->orderBy('clients.emirate')
                        ->pluck('clients.emirate', 'clients.emirate')
                        ->all()
                    )
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $q): Builder => $q->whereExists(fn ($client) =>
                                $client->selectRaw('1')
                                    ->from('clients')
                                    ->whereColumn('clients.id', 'client_phone_numbers.client_id')
                                    ->where('clients.emirate', $data['value'])
                            )
                        )
                    )
                    ->searchable(),

                SelectFilter::make('marketing_area')
                    ->label('Marketing Area')
                    ->options(fn () => MarketingArea::active()->orderBy('emirate')->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $q): Builder => $q->whereExists(fn ($ownership) =>
                                $ownership->selectRaw('1')
                                    ->from('ownerships')
                                    ->whereColumn('ownerships.client_id', 'client_phone_numbers.client_id')
                                    ->where('ownerships.marketing_area_id', $data['value'])
                            )
                        )
                    )
                    ->searchable(),

                SelectFilter::make('last_message_status')
                    ->label('Last Message Status')
                    ->options([
                        'SENT'      => 'Sent',
                        'DELIVERED' => 'Delivered',
                        'READ'      => 'Read',
                        'REPLIED'   => 'Replied',
                        'FAILED'    => 'Failed',
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $q): Builder => $q->whereHas('whatsAppProfile', fn ($p) =>
                                $p->whereRaw('upper(last_message_status) = ?', [strtoupper($data['value'])])
                            )
                        )
                    ),

                SelectFilter::make('campaign_history')
                    ->label('Campaign History')
                    ->options([
                        'messaged' => 'Previously messaged',
                        'new'      => 'Never messaged',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'messaged' => $query->whereHas('whatsAppMessages'),
                        'new'      => $query->whereDoesntHave('whatsAppMessages'),
                        default    => $query,
                    }),

                SelectFilter::make('country')
                    ->label('Country (non-UAE)')
                    ->options(fn () => ClientPhoneNumber::query()
                        ->where('is_uae', false)
                        ->whereNotNull('detected_country')
                        ->whereRaw("trim(detected_country) <> ''")
                        ->distinct()
                        ->orderBy('detected_country')
                        ->pluck('detected_country', 'detected_country')
                        ->all()
                    )
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $q): Builder => $q
                                ->where('is_uae', false)
                                ->where('detected_country', $data['value'])
                        )
                    )
                    ->searchable(),

                Filter::make('is_lead')
                    ->label('Leads Only')
                    ->query(fn (Builder $query): Builder => $query->where('is_whatsapp_lead', true)),

                Filter::make('suppressed')
                    ->label('Suppressed Only')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereExists(fn ($q) =>
                            $q->selectRaw('1')
                                ->from('contact_suppressions')
                                ->whereColumn('contact_suppressions.client_phone_number_id', 'client_phone_numbers.id')
                                ->where('contact_suppressions.channel', 'whatsapp')
                                ->whereNull('contact_suppressions.released_at')
                        )
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('suppress')
                    ->label('Suppress')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (ClientPhoneNumber $record) => ! (bool) $record->is_whatsapp_suppressed)
                    ->form([
                        Textarea::make('reason')->label('Reason (optional)')->rows(2),
                    ])
                    ->action(function (ClientPhoneNumber $record, array $data): void {
                        ContactSuppression::create([
                            'client_phone_number_id' => $record->id,
                            'channel'                => 'whatsapp',
                            'reason'                 => 'manual',
                            'suppressed_at'          => now(),
                            'context'                => filled($data['reason'] ?? null)
                                ? ['reason' => $data['reason']]
                                : null,
                        ]);
                        Notification::make()->title('Number suppressed for WhatsApp')->warning()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Suppress for WhatsApp'),

                Action::make('unsuppress')
                    ->label('Unsuppress')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ClientPhoneNumber $record) => (bool) $record->is_whatsapp_suppressed)
                    ->action(function (ClientPhoneNumber $record): void {
                        ContactSuppression::where('client_phone_number_id', $record->id)
                            ->where('channel', 'whatsapp')
                            ->whereNull('released_at')
                            ->update(['released_at' => now()]);
                        Notification::make()->title('Number unsuppressed for WhatsApp')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Unsuppress this number for WhatsApp?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_suppress')
                        ->label('Suppress selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if (! (bool) $record->is_whatsapp_suppressed) {
                                    ContactSuppression::create([
                                        'client_phone_number_id' => $record->id,
                                        'channel'                => 'whatsapp',
                                        'reason'                 => 'manual',
                                        'suppressed_at'          => now(),
                                    ]);
                                    $count++;
                                }
                            }
                            Notification::make()->title("Suppressed {$count} number(s) for WhatsApp")->warning()->send();
                        }),
                ]),
            ]);
    }
}
