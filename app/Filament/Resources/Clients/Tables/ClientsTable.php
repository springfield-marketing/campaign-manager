<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Models\ClientPhoneNumber;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
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
                        'vip'            => 'VIP',
                        'high_net_worth' => 'High Net Worth',
                        'premium'        => 'Premium',
                        'standard'       => 'Standard',
                        default          => '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'vip'            => 'warning',
                        'high_net_worth' => 'success',
                        'premium'        => 'info',
                        'standard'       => 'gray',
                        default          => 'gray',
                    })
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('completeness_score')
                    ->label('Completeness')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? $state . '%' : '—')
                    ->sortable()
                    ->color(fn (?int $state) => match (true) {
                        $state === null  => 'gray',
                        $state >= 75     => 'success',
                        $state >= 50     => 'warning',
                        default          => 'danger',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('emirate')
                    ->options([
                        'Dubai'          => 'Dubai',
                        'Abu Dhabi'      => 'Abu Dhabi',
                        'Sharjah'        => 'Sharjah',
                        'Ajman'          => 'Ajman',
                        'Ras Al Khaimah' => 'Ras Al Khaimah',
                        'Fujairah'       => 'Fujairah',
                        'Umm Al Quwain'  => 'Umm Al Quwain',
                    ]),

                SelectFilter::make('country_iso')
                    ->label('Country')
                    ->options(fn () =>
                        \App\Models\Client::whereNotNull('country_iso')
                            ->distinct()
                            ->orderBy('country_iso')
                            ->pluck('country_iso', 'country_iso')
                            ->all()
                    ),

                SelectFilter::make('tier')
                    ->options([
                        'vip'            => 'VIP',
                        'high_net_worth' => 'High Net Worth',
                        'premium'        => 'Premium',
                        'standard'       => 'Standard',
                    ])
                    ->placeholder('All tiers'),

                Filter::make('has_phone')
                    ->label('Has Phone Number')
                    ->query(fn (Builder $query) => $query->whereHas('phoneNumbers')),

                Filter::make('has_ownership')
                    ->label('Has Property')
                    ->query(fn (Builder $query) => $query->whereHas('ownerships')),

                Filter::make('phone_search')
                    ->label('Search by Phone')
                    ->form([
                        TextInput::make('phone')
                            ->label('Phone number')
                            ->placeholder('+971501234567'),
                    ])
                    ->query(fn (Builder $query, array $data) =>
                        $query->when(
                            filled($data['phone'] ?? null),
                            fn ($q) => $q->whereHas('phoneNumbers', fn ($q2) =>
                                $q2->where('normalized_phone', 'like', '%'.ltrim($data['phone'], '+').'%')
                                   ->orWhere('raw_phone', 'like', '%'.$data['phone'].'%')
                            )
                        )
                    ),
            ])
            ->defaultSort('full_name')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
