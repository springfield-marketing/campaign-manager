<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Models\Client;
use App\Support\Identity\ClientSplitter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('primaryEmail.email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),

                TextColumn::make('emirate')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('country_iso')
                    ->label('Country')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('phone_numbers_count')
                    ->label('Phones')
                    ->counts('phoneNumbers')
                    ->sortable(),

                TextColumn::make('ownerships_count')
                    ->label('Properties')
                    ->counts('ownerships')
                    ->sortable(),

                TextColumn::make('tier')
                    ->label('Tier')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'vip' => 'VIP',
                        'high_net_worth' => 'High Net Worth',
                        'premium' => 'Premium',
                        'standard' => 'Standard',
                        default => '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'vip' => 'warning',
                        'high_net_worth' => 'success',
                        'premium' => 'info',
                        'standard' => 'gray',
                        default => 'gray',
                    })
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('completeness_score')
                    ->label('Completeness')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? $state.'%' : '—')
                    ->sortable()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 75 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('original_source')
                    ->label('Original Source')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
            ])
            ->filters([
                // IMP-003: organisation names (developers, banks, "…L.L.C") are not marketing
                // contacts — they absorbed thousands of units as owner-of-record. Hide them by
                // default; switch to "Institutions" or clear to "All" to review them.
                SelectFilter::make('is_institution')
                    ->label('Contact Type')
                    ->options([
                        '0' => 'People',
                        '1' => 'Institutions (developers, banks)',
                    ])
                    ->default('0')
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->where('is_institution', $data['value'] === '1'),
                    )),

                SelectFilter::make('emirate')
                    ->options([
                        'Dubai' => 'Dubai',
                        'Abu Dhabi' => 'Abu Dhabi',
                        'Sharjah' => 'Sharjah',
                        'Ajman' => 'Ajman',
                        'Ras Al Khaimah' => 'Ras Al Khaimah',
                        'Fujairah' => 'Fujairah',
                        'Umm Al Quwain' => 'Umm Al Quwain',
                    ]),

                SelectFilter::make('country_iso')
                    ->label('Country')
                    ->options(fn () => Client::whereNotNull('country_iso')
                        ->distinct()
                        ->orderBy('country_iso')
                        ->pluck('country_iso', 'country_iso')
                        ->all()
                    ),

                SelectFilter::make('tier')
                    ->options([
                        'vip' => 'VIP',
                        'high_net_worth' => 'High Net Worth',
                        'premium' => 'Premium',
                        'standard' => 'Standard',
                    ])
                    ->placeholder('All tiers'),

                Filter::make('has_phone')
                    ->label('Has Phone Number')
                    ->query(fn (Builder $query) => $query->whereHas('phoneNumbers')),

                Filter::make('has_ownership')
                    ->label('Has Property')
                    ->query(fn (Builder $query) => $query->whereHas('ownerships')),

                // Surfaces "super clients" — one record holding multiple distinct numbers, the
                // signature of a pre-IMP-003 bad merge (a bank/stub/shared name that absorbed many
                // unrelated people). Review and split via the row's Split action.
                Filter::make('multi_number')
                    ->label('Multiple numbers (possible bad merge)')
                    ->query(fn (Builder $query) => $query->whereHas('phoneNumbers', null, '>=', 2)),

                Filter::make('phone_search')
                    ->label('Search by Phone')
                    ->form([
                        TextInput::make('phone')
                            ->label('Phone number')
                            ->placeholder('+971501234567'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['phone'] ?? null),
                        fn ($q) => $q->whereHas('phoneNumbers', fn ($q2) => $q2->where('normalized_phone', 'like', '%'.ltrim($data['phone'], '+').'%')
                            ->orWhere('raw_phone', 'like', '%'.$data['phone'].'%')
                        )
                    )
                    ),
            ])
            ->defaultSort('full_name')
            ->recordActions([
                EditAction::make(),

                // Manual remediation for a pre-IMP-003 super-client: split one record holding many
                // unrelated numbers into one client per phone. Audit-only by policy — never
                // automatic; the operator reviews each client and triggers this deliberately.
                Action::make('split_phones')
                    ->label('Split numbers')
                    ->icon('heroicon-o-scissors')
                    ->color('warning')
                    ->visible(fn (Client $record): bool => ($record->phone_numbers_count ?? $record->phoneNumbers()->count()) > 1)
                    ->requiresConfirmation()
                    ->modalHeading('Split this contact into one contact per number')
                    ->modalDescription(fn (Client $record): string => "\"{$record->full_name}\" holds ".
                        ($record->phone_numbers_count ?? $record->phoneNumbers()->count()).
                        ' phone numbers. Review the plan below before splitting.')
                    // Dry-run preview: shows the anchor, the recovered name per moved number, and
                    // the campaign history that travels with each — computed without mutating.
                    ->modalContent(fn (Client $record) => view('filament.actions.split-preview', [
                        'plan' => app(ClientSplitter::class)->preview($record),
                    ]))
                    ->modalSubmitActionLabel('Split')
                    ->action(function (Client $record): void {
                        $result = app(ClientSplitter::class)->split($record);

                        Notification::make()
                            ->title('Contact split')
                            ->body("Kept the anchor number; moved {$result['moved']} number(s) to their own contact".
                                ($result['deleted'] > 0 ? ", removed {$result['deleted']} placeholder number(s)." : '.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
