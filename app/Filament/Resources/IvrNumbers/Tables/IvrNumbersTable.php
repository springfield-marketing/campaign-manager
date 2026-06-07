<?php

namespace App\Filament\Resources\IvrNumbers\Tables;

use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\MarketingArea;
use App\Modules\IVR\Support\NumberEligibilityService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class IvrNumbersTable
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

                TextColumn::make('ivrProfile.usage_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match($state) {
                        'active'   => 'success',
                        'cooldown' => 'warning',
                        'dead'     => 'danger',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => $state ?? 'active'),

                TextColumn::make('ivrProfile.cooldown_until')
                    ->label('Cooldown Until')
                    ->dateTime('d M Y')
                    ->placeholder('—'),

                TextColumn::make('ivrProfile.last_called_at')
                    ->label('Last Called')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('ivr_call_records_count')
                    ->label('Calls')
                    ->counts('ivrCallRecords')
                    ->sortable(),

                IconColumn::make('is_ivr_suppressed')
                    ->label('Suppressed')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('usage_status')
                    ->label('IVR Status')
                    ->options([
                        'active'   => 'Active',
                        'cooldown' => 'Cooldown',
                        'dead'     => 'Dead',
                    ])
                    ->query(fn (Builder $q, array $data) =>
                        $q->when(
                            filled($data['value'] ?? null),
                            fn ($q) => $q->whereHas('ivrProfile', fn ($p) => $p->where('usage_status', $data['value']))
                        )
                    ),

                SelectFilter::make('marketing_area')
                    ->label('Marketing Area')
                    ->options(fn () => MarketingArea::active()->orderBy('emirate')->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $q, array $data) =>
                        $q->when(
                            filled($data['value'] ?? null),
                            fn ($q) => $q->whereHas('client.ownerships', fn ($o) =>
                                $o->where('marketing_area_id', $data['value'])
                            )
                        )
                    )
                    ->searchable(),

                Filter::make('suppressed')
                    ->label('Suppressed Only')
                    ->query(fn (Builder $q) =>
                        $q->whereHas('suppressions', fn ($s) =>
                            $s->where('channel', 'ivr')->whereNull('released_at')
                        )
                    ),

                Filter::make('not_suppressed')
                    ->label('Active (not suppressed)')
                    ->query(fn (Builder $q) =>
                        $q->whereDoesntHave('suppressions', fn ($s) =>
                            $s->where('channel', 'ivr')->whereNull('released_at')
                        )
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('suppress')
                    ->label('Suppress')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (ClientPhoneNumber $record) => ! $record->is_ivr_suppressed)
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason (optional)')
                            ->rows(2),
                    ])
                    ->action(function (ClientPhoneNumber $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            ContactSuppression::firstOrCreate(
                                [
                                    'client_phone_number_id' => $record->id,
                                    'channel'                => 'ivr',
                                    'reason'                 => 'customer_unsubscribed',
                                ],
                                [
                                    'suppressed_at' => now(),
                                    'context'       => ['source' => 'manual', 'reason' => $data['reason'] ?? null],
                                ],
                            );
                            $record->forceFill(['unsubscribed_at' => $record->unsubscribed_at ?? now()])->save();
                            app(NumberEligibilityService::class)->refresh($record->refresh());
                        });
                        Notification::make()->title('Number suppressed')->warning()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Suppress for IVR'),

                Action::make('unsuppress')
                    ->label('Unsuppress')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ClientPhoneNumber $record) => (bool) $record->is_ivr_suppressed)
                    ->action(function (ClientPhoneNumber $record): void {
                        DB::transaction(function () use ($record): void {
                            ContactSuppression::where('client_phone_number_id', $record->id)
                                ->where('channel', 'ivr')
                                ->where('reason', 'customer_unsubscribed')
                                ->whereNull('released_at')
                                ->update(['released_at' => now()]);

                            $hasOtherActiveSuppression = $record->suppressions()
                                ->whereNull('released_at')
                                ->where(fn (Builder $q) => $q->whereNull('channel')->orWhere('channel', 'ivr'))
                                ->exists();

                            if (! $hasOtherActiveSuppression) {
                                $record->forceFill(['unsubscribed_at' => null])->save();
                            }

                            app(NumberEligibilityService::class)->refresh($record->refresh());
                        });
                        Notification::make()->title('Number unsuppressed')->success()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Unsuppress this number?'),
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
            ->label('Suppress selected')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                $ids = $records->pluck('id')->all();

                $alreadySuppressedIds = ContactSuppression::query()
                    ->whereIn('client_phone_number_id', $ids)
                    ->where('channel', 'ivr')
                    ->whereNull('released_at')
                    ->pluck('client_phone_number_id')
                    ->all();

                $toSuppress = $records->reject(fn ($r) => in_array($r->id, $alreadySuppressedIds, true));

                $now = now();
                DB::transaction(function () use ($toSuppress, $now): void {
                    foreach ($toSuppress as $record) {
                        ContactSuppression::firstOrCreate(
                            [
                                'client_phone_number_id' => $record->id,
                                'channel'                => 'ivr',
                                'reason'                 => 'customer_unsubscribed',
                            ],
                            ['suppressed_at' => $now, 'context' => ['source' => 'manual_bulk']],
                        );
                        $record->forceFill(['unsubscribed_at' => $record->unsubscribed_at ?? $now])->save();
                    }
                });

                foreach ($toSuppress as $record) {
                    app(NumberEligibilityService::class)->refresh($record->refresh());
                }

                Notification::make()->title("Suppressed {$toSuppress->count()} number(s)")->warning()->send();
            });
    }
}
