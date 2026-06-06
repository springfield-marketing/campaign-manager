<?php

namespace App\Filament\Resources\IvrNumbers\Tables;

use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Models\MarketingArea;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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

                IconColumn::make('is_suppressed')
                    ->label('Suppressed')
                    ->boolean()
                    ->getStateUsing(fn (ClientPhoneNumber $record) =>
                        $record->suppressions()
                            ->where('channel', 'ivr')
                            ->whereNull('released_at')
                            ->exists()
                    ),
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
                    ->visible(fn (ClientPhoneNumber $record) =>
                        ! ContactSuppression::where('client_phone_number_id', $record->id)
                            ->where('channel', 'ivr')
                            ->whereNull('released_at')
                            ->exists()
                    )
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason (optional)')
                            ->rows(2),
                    ])
                    ->action(function (ClientPhoneNumber $record, array $data): void {
                        ContactSuppression::create([
                            'client_phone_number_id' => $record->id,
                            'channel'                => 'ivr',
                            'suppressed_at'          => now(),
                            'context'                => $data['reason'] ? ['reason' => $data['reason']] : null,
                        ]);
                        Notification::make()->title('Number suppressed')->warning()->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Suppress for IVR'),

                Action::make('unsuppress')
                    ->label('Unsuppress')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ClientPhoneNumber $record) =>
                        ContactSuppression::where('client_phone_number_id', $record->id)
                            ->where('channel', 'ivr')
                            ->whereNull('released_at')
                            ->exists()
                    )
                    ->action(function (ClientPhoneNumber $record): void {
                        ContactSuppression::where('client_phone_number_id', $record->id)
                            ->where('channel', 'ivr')
                            ->whereNull('released_at')
                            ->update(['released_at' => now()]);
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
                $count = 0;
                foreach ($records as $record) {
                    $alreadySuppressed = ContactSuppression::where('client_phone_number_id', $record->id)
                        ->where('channel', 'ivr')
                        ->whereNull('released_at')
                        ->exists();

                    if (! $alreadySuppressed) {
                        ContactSuppression::create([
                            'client_phone_number_id' => $record->id,
                            'channel'                => 'ivr',
                            'suppressed_at'          => now(),
                        ]);
                        $count++;
                    }
                }
                Notification::make()->title("Suppressed $count number(s)")->warning()->send();
            });
    }
}
