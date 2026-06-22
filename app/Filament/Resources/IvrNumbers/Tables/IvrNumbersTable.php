<?php

namespace App\Filament\Resources\IvrNumbers\Tables;

use App\Filament\Filters\PhoneSearchFilter;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\ClientPhoneNumber;
use App\Models\MarketingArea;
use App\Models\Tag;
use App\Modules\IVR\Support\IvrSuppressionService;
use App\Support\IvrSuppressionDisplay;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IvrNumbersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('normalized_phone')
                    ->label('Phone')
                    ->searchable(query: fn (Builder $query, string $search): Builder =>
                        $query->whereIn('client_phone_numbers.normalized_phone', PhoneSearchFilter::candidates($search)))
                    ->url(fn (ClientPhoneNumber $record): ?string => $record->client_id
                        ? ClientResource::getUrl('edit', ['record' => $record->client_id])
                        : null),

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

                TextColumn::make('effective_calling_status')
                    ->label('Calling Status')
                    ->badge()
                    ->color(fn (?string $state) => IvrSuppressionDisplay::statusColor($state))
                    ->formatStateUsing(fn (?string $state) => IvrSuppressionDisplay::statusLabel($state))
                    ->getStateUsing(fn (ClientPhoneNumber $record): string => $record->effectiveCallingStatus()),

                TextColumn::make('ivrProfile.cooldown_until')
                    ->label('Rest Until')
                    ->dateTime('d M Y')
                    ->placeholder('—'),

                TextColumn::make('ivrProfile.last_called_at')
                    ->label('Last Called')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('client.tags.name')
                    ->label('Tags')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ivr_call_records_count')
                    ->label('Calls')
                    ->counts('ivrCallRecords')
                    ->sortable(),

                IconColumn::make('is_ivr_suppressed')
                    ->label('Do Not Call')
                    ->boolean(),

                TextColumn::make('active_suppression_reason')
                    ->label('Do Not Call Reason')
                    ->getStateUsing(fn (ClientPhoneNumber $record): string => self::activeSuppressionReason($record))
                    ->badge()
                    ->color('danger')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reentered_while_suppressed_at')
                    ->label('DNC Re-entry')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                PhoneSearchFilter::make('phone', fn (Builder $query, array $candidates) =>
                    // Exact match on the unique `normalized_phone` index — a `LIKE '%…%'` here forces a
                    // full sequential scan of ~870k phone numbers, which times out the request under load.
                    $query->whereIn('client_phone_numbers.normalized_phone', $candidates)
                ),

                SelectFilter::make('usage_status')
                    ->label('Calling Status')
                    ->options([
                        'active'   => 'Ready to Call',
                        'inactive' => 'Resting',
                        'dead'     => 'Not Callable',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active'   => $query->readyToCall(),
                        'inactive' => $query->resting(),
                        'dead'     => $query->notCallable(),
                        default    => $query,
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
                    ->query(fn (Builder $query, array $data) =>
                        $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $query): Builder => $query->whereExists(fn ($client) =>
                                $client->selectRaw('1')
                                    ->from('clients')
                                    ->whereColumn('clients.id', 'client_phone_numbers.client_id')
                                    ->where('clients.emirate', $data['value'])
                            )
                        )
                    )
                    ->searchable(),

                SelectFilter::make('communities')
                    ->label('Communities')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn () => MarketingArea::active()
                        ->orderBy('emirate')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (MarketingArea $area) => [
                            $area->id => "{$area->emirate} - {$area->name}",
                        ])
                        ->all()
                    )
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            filled($data['values'] ?? []),
                            fn (Builder $query): Builder => $query->whereExists(fn ($ownership) =>
                                $ownership->selectRaw('1')
                                    ->from('ownerships')
                                    ->whereColumn('ownerships.client_id', 'client_phone_numbers.client_id')
                                    ->whereIn('ownerships.marketing_area_id', $data['values'])
                            )
                        )
                    ),

                SelectFilter::make('relationship_types')
                    ->label('Relationship')
                    ->multiple()
                    ->options(self::relationshipTypeOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            filled($data['values'] ?? []),
                            fn (Builder $query): Builder => $query->whereExists(fn ($ownership) =>
                                $ownership->selectRaw('1')
                                    ->from('ownerships')
                                    ->whereColumn('ownerships.client_id', 'client_phone_numbers.client_id')
                                    ->whereIn('ownerships.relationship_type', $data['values'])
                            )
                        )
                    ),

                SelectFilter::make('tags')
                    ->label('Tags')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn () => Tag::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when(
                            filled($data['values'] ?? []),
                            fn (Builder $query): Builder => $query->whereExists(fn ($tag) =>
                                $tag->selectRaw('1')
                                    ->from('client_tags')
                                    ->whereColumn('client_tags.client_id', 'client_phone_numbers.client_id')
                                    ->whereIn('client_tags.tag_id', $data['values'])
                            )
                        )
                    ),

                SelectFilter::make('campaign_history')
                    ->label('Campaign History')
                    ->options([
                        'called' => 'Previously called',
                        'new'    => 'Never called',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'called' => $query->whereHas('ivrCallRecords'),
                        'new'    => $query->whereDoesntHave('ivrCallRecords'),
                        default  => $query,
                    }),

                // Re-entry audit: numbers re-imported in a raw list AFTER being put on the
                // Do-Not-Call list. They stay suppressed/uncallable; this just surfaces them.
                Filter::make('dnc_reentry')
                    ->label('Re-entered after Do Not Call')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('reentered_while_suppressed_at')),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->defaultSort('created_at', 'desc')
            // ~1M rows: defer the query and use simple pagination to avoid a COUNT(*) over the
            // filtered set on every load. Totals are shown by the stats widget.
            ->deferLoading()
            ->paginationMode(PaginationMode::Simple)
            ->recordActions([
                Action::make('suppress')
                    ->label('Mark Do Not Call')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (ClientPhoneNumber $record) => ! $record->is_ivr_suppressed)
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->placeholder('Requested opt out, wrong audience, complaint, etc.')
                            ->rows(2),
                    ])
                    ->action(function (ClientPhoneNumber $record, array $data): void {
                        app(IvrSuppressionService::class)->suppress($record, $data['reason'] ?? null);
                        Notification::make()->title('Number marked Do Not Call')->warning()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark this number Do Not Call for IVR'),

                Action::make('unsuppress')
                    ->label('Make Callable')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ClientPhoneNumber $record) => (bool) $record->is_ivr_suppressed)
                    ->action(function (ClientPhoneNumber $record): void {
                        app(IvrSuppressionService::class)->unsuppress($record);
                        Notification::make()->title('Number can be called again')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Make this number callable again?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkSuppressAction(),
                ]),
            ]);
    }

    private static function bulkSuppressAction(): \Filament\Actions\BulkAction
    {
        return \Filament\Actions\BulkAction::make('bulk_suppress')
            ->label('Mark selected Do Not Call')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                $count = app(IvrSuppressionService::class)->bulkSuppress($records);
                Notification::make()->title("Marked {$count} number(s) Do Not Call")->warning()->send();
            });
    }

    private static function activeSuppressionReason(ClientPhoneNumber $record): string
    {
        $suppression = $record->suppressions()->activeIvr()->latest('suppressed_at')->first();

        return $suppression ? IvrSuppressionDisplay::reasonLabel($suppression->reason) : '—';
    }

    private static function relationshipTypeOptions(): array
    {
        return [
            'owner'            => 'Owner',
            'resident'         => 'Resident',
            'tenant'           => 'Tenant',
            'buyer_interest'   => 'Buyer Interest',
            'seller_interest'  => 'Seller Interest',
            'investor'         => 'Investor',
            'past_owner'       => 'Past Owner',
            'unknown'          => 'Unknown',
        ];
    }
}
