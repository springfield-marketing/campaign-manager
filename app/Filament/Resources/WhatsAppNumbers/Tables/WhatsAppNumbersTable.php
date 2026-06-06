<?php

namespace App\Filament\Resources\WhatsAppNumbers\Tables;

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

                TextColumn::make('whatsAppProfile.usage_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match($state) {
                        'active'   => 'success',
                        'cooldown' => 'warning',
                        'dead'     => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ?? 'active'),

                TextColumn::make('whatsAppProfile.cooldown_until')
                    ->label('Cooldown Until')
                    ->dateTime('d M Y')
                    ->placeholder('—'),

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
                    ->color(fn (?string $state) => match($state) {
                        'delivered', 'read' => 'success',
                        'sent'              => 'info',
                        'failed'            => 'danger',
                        default             => 'gray',
                    })
                    ->placeholder('—'),

                IconColumn::make('is_suppressed')
                    ->label('Suppressed')
                    ->boolean()
                    ->getStateUsing(fn (ClientPhoneNumber $record) =>
                        $record->suppressions()
                            ->where('channel', 'whatsapp')
                            ->whereNull('released_at')
                            ->exists()
                    ),
            ])
            ->filters([
                SelectFilter::make('usage_status')
                    ->label('Status')
                    ->options([
                        'active'   => 'Active',
                        'cooldown' => 'Cooldown',
                        'dead'     => 'Dead',
                    ])
                    ->query(fn (Builder $q, array $data) =>
                        $q->when(
                            filled($data['value'] ?? null),
                            fn ($q) => $q->whereHas('whatsAppProfile',
                                fn ($p) => $p->where('usage_status', $data['value'])
                            )
                        )
                    ),

                SelectFilter::make('marketing_area')
                    ->label('Marketing Area')
                    ->options(fn () => MarketingArea::active()->orderBy('emirate')->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $q, array $data) =>
                        $q->when(
                            filled($data['value'] ?? null),
                            fn ($q) => $q->whereHas('client.ownerships',
                                fn ($o) => $o->where('marketing_area_id', $data['value'])
                            )
                        )
                    )
                    ->searchable(),

                SelectFilter::make('last_message_status')
                    ->label('Last Message Status')
                    ->options([
                        'sent'      => 'Sent',
                        'delivered' => 'Delivered',
                        'read'      => 'Read',
                        'failed'    => 'Failed',
                    ])
                    ->query(fn (Builder $q, array $data) =>
                        $q->when(
                            filled($data['value'] ?? null),
                            fn ($q) => $q->whereHas('whatsAppProfile',
                                fn ($p) => $p->where('last_message_status', $data['value'])
                            )
                        )
                    ),

                Filter::make('suppressed')
                    ->label('Suppressed Only')
                    ->query(fn (Builder $q) =>
                        $q->whereHas('suppressions', fn ($s) =>
                            $s->where('channel', 'whatsapp')->whereNull('released_at')
                        )
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('suppress')
                    ->label('Suppress')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (ClientPhoneNumber $record) =>
                        ! ContactSuppression::where('client_phone_number_id', $record->id)
                            ->where('channel', 'whatsapp')
                            ->whereNull('released_at')
                            ->exists()
                    )
                    ->form([
                        Textarea::make('reason')->label('Reason (optional)')->rows(2),
                    ])
                    ->action(function (ClientPhoneNumber $record, array $data): void {
                        ContactSuppression::create([
                            'client_phone_number_id' => $record->id,
                            'channel'                => 'whatsapp',
                            'suppressed_at'          => now(),
                            'context'                => $data['reason'] ? ['reason' => $data['reason']] : null,
                        ]);
                        Notification::make()->title('Number suppressed')->warning()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Suppress for WhatsApp'),

                Action::make('unsuppress')
                    ->label('Unsuppress')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ClientPhoneNumber $record) =>
                        ContactSuppression::where('client_phone_number_id', $record->id)
                            ->where('channel', 'whatsapp')
                            ->whereNull('released_at')
                            ->exists()
                    )
                    ->action(function (ClientPhoneNumber $record): void {
                        ContactSuppression::where('client_phone_number_id', $record->id)
                            ->where('channel', 'whatsapp')
                            ->whereNull('released_at')
                            ->update(['released_at' => now()]);
                        Notification::make()->title('Number unsuppressed')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Unsuppress this number?'),
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
                                $exists = ContactSuppression::where('client_phone_number_id', $record->id)
                                    ->where('channel', 'whatsapp')->whereNull('released_at')->exists();
                                if (! $exists) {
                                    ContactSuppression::create([
                                        'client_phone_number_id' => $record->id,
                                        'channel'                => 'whatsapp',
                                        'suppressed_at'          => now(),
                                    ]);
                                    $count++;
                                }
                            }
                            Notification::make()->title("Suppressed $count number(s)")->warning()->send();
                        }),
                ]),
            ]);
    }
}
