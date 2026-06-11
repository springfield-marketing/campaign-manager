<?php

namespace App\Filament\Resources\RawImports\RelationManagers;

use App\Models\ClientSource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImportedContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'rawImportSources';
    protected static ?string $title = 'Imported Contacts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'client.primaryPhone',
                'client.primaryEmail',
            ]))
            ->columns([
                TextColumn::make('is_duplicate')
                    ->label('Status')
                    ->getStateUsing(fn (ClientSource $record): string =>
                        ($record->metadata['duplicate'] ?? false) ? 'duplicate' : 'new'
                    )
                    ->badge()
                    ->color(fn (string $state) => $state === 'new' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                TextColumn::make('client.full_name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('client.primaryPhone.normalized_phone')
                    ->label('Phone')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('client.primaryEmail.email')
                    ->label('Email')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('client.emirate')
                    ->label('Emirate')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('client.tier')
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
                        default          => 'gray',
                    })
                    ->placeholder('—'),

                TextColumn::make('client.completeness_score')
                    ->label('Completeness')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? $state . '%' : '—')
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 75   => 'success',
                        $state >= 50   => 'warning',
                        default        => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Imported')
                    ->dateTime('d M H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['new' => 'New contacts', 'duplicate' => 'Duplicates'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'new'       => $query->whereJsonContains('metadata->duplicate', false),
                        'duplicate' => $query->whereJsonContains('metadata->duplicate', true),
                        default     => $query,
                    }),

                SelectFilter::make('emirate')
                    ->label('Emirate')
                    ->relationship('client', 'emirate')
                    ->options([
                        'Dubai'          => 'Dubai',
                        'Abu Dhabi'      => 'Abu Dhabi',
                        'Sharjah'        => 'Sharjah',
                        'Ajman'          => 'Ajman',
                        'Ras Al Khaimah' => 'Ras Al Khaimah',
                        'Fujairah'       => 'Fujairah',
                        'Umm Al Quwain'  => 'Umm Al Quwain',
                    ]),

                SelectFilter::make('tier')
                    ->label('Tier')
                    ->relationship('client', 'tier')
                    ->options([
                        'vip'            => 'VIP',
                        'high_net_worth' => 'High Net Worth',
                        'premium'        => 'Premium',
                        'standard'       => 'Standard',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordAction(null)
            ->recordUrl(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
