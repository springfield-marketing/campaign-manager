<?php

namespace App\Filament\Resources\WhatsAppUnsubscribers\Tables;

use App\Filament\Filters\PhoneSearchFilter;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\WhatsAppUnsubscribers\WhatsAppUnsubscriberResource;
use App\Models\ContactSuppression;
use App\Support\WhatsAppSuppressionDisplay;
use Filament\Tables\Filters\SelectFilter;
use App\Modules\WhatsApp\Enums\WhatsAppPlatform;
use App\Modules\WhatsApp\Support\WhatsAppNumberResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsAppUnsubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phoneNumber.client.full_name')
                    ->label('Name')
                    ->placeholder('—'),

                TextColumn::make('phoneNumber.normalized_phone')
                    ->label('Phone')
                    ->url(fn (ContactSuppression $record): ?string => $record->phoneNumber?->client_id
                        ? ClientResource::getUrl('edit', ['record' => $record->phoneNumber->client_id])
                        : null)
                    ->placeholder('—'),

                TextColumn::make('suppressed_at')
                    ->label('Suppressed At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('source')
                    ->label('Source')
                    ->getStateUsing(fn (ContactSuppression $record): string => WhatsAppSuppressionDisplay::sourceLabel($record))
                    ->placeholder('—'),

                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => WhatsAppPlatform::tryFrom($state ?? '')?->getLabel() ?? '—')
                    ->placeholder('—'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => WhatsAppSuppressionDisplay::reasonLabel($state))
                    ->color('warning'),
            ])
            ->defaultSort('suppressed_at', 'desc')
            ->filters([
                Filter::make('phone')
                    ->form([
                        TextInput::make('phone')->label('Phone')->placeholder('+971501234567'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $phone = trim((string) ($data['phone'] ?? ''));

                        if ($phone === '') {
                            return $query;
                        }

                        // Resolve the same way the import does, so a legacy format (e.g. the Mexico
                        // dropped "1") finds the suppression on the canonical record it was stored under.
                        if ($resolved = app(WhatsAppNumberResolver::class)->resolveExisting($phone)) {
                            return $query->where('client_phone_number_id', $resolved->id);
                        }

                        // Exact match on the unique normalized_phone index (a LIKE would force a full
                        // scan) for inputs the resolver can't pin to one record.
                        return $query->whereHas('phoneNumber', fn (Builder $q) =>
                            $q->whereIn('normalized_phone', PhoneSearchFilter::candidates($phone)));
                    }),

                Filter::make('name')
                    ->form([TextInput::make('name')->label('Search name')])
                    ->query(fn (Builder $query, array $data) =>
                        $query->when(filled($data['name']), fn ($q) =>
                            $q->whereHas('phoneNumber.client', fn ($q) =>
                                $q->where('full_name', 'like', '%'.$data['name'].'%')
                            )
                        )
                    ),

                SelectFilter::make('platform')
                    ->label('Platform')
                    ->options(array_merge(['__global' => 'Global (all platforms)'], WhatsAppPlatform::options()))
                    ->query(fn (Builder $query, array $data) =>
                        $query->when(filled($data['value']), fn ($q) =>
                            $data['value'] === '__global'
                                ? $q->whereNull('platform')
                                : $q->where('platform', $data['value'])
                        )
                    ),
            ])
            // CSV upload lives on the WhatsApp Imports page; this page only adds single numbers.
            ->headerActions([
                Action::make('add_single')
                    ->label('Add Do Not Message Number')
                    ->icon('heroicon-o-plus')
                    ->color('warning')
                    ->form([
                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->required()
                            ->placeholder('+971501234567')
                            ->helperText('Enter in any format. The number must already exist in the database.'),
                    ])
                    ->action(function (array $data): void {
                        // Match an existing number (tolerating legacy formats that no longer
                        // validate); these are campaign'd numbers already on file.
                        $number = app(WhatsAppNumberResolver::class)->resolveExisting($data['phone']);

                        if (! $number) {
                            Notification::make()
                                ->title("No number on file matches \"{$data['phone']}\".")
                                ->body('Only numbers already in the database can be added here. Use the CSV import on the Imports page for new numbers.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $exists = ContactSuppression::where('client_phone_number_id', $number->id)
                            ->where('channel', 'whatsapp')
                            ->whereNull('released_at')
                            ->exists();

                        if ($exists) {
                            Notification::make()->title("Number {$number->normalized_phone} is already suppressed for WhatsApp.")->warning()->send();
                            return;
                        }

                        ContactSuppression::create([
                            'client_phone_number_id' => $number->id,
                            'channel'                => 'whatsapp',
                            'reason'                 => 'manual',
                            'suppressed_at'          => now(),
                            'context'                => ['source' => 'manual', 'added_by' => auth()->id()],
                        ]);

                        Notification::make()->title("Number {$number->normalized_phone} added to WhatsApp Do Not Message list.")->success()->send();
                    })
                    ->modalHeading('Add a number to WhatsApp Do Not Message list'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (ContactSuppression $record): string =>
                        WhatsAppUnsubscriberResource::getUrl('view', ['record' => $record])),

                Action::make('release')
                    ->label('Make Messageable')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Allow this number to receive WhatsApp messages again?')
                    ->modalDescription('This removes the active suppression. The number will be eligible for future campaigns.')
                    ->action(function (ContactSuppression $record): void {
                        $record->forceFill(['released_at' => now()])->save();

                        Notification::make()->title('Number can receive WhatsApp messages again.')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
