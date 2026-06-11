<?php

namespace App\Filament\Resources\RawImports\RelationManagers;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\ClientSource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

                TextColumn::make('conflicts')
                    ->label('Conflicts')
                    ->getStateUsing(function (ClientSource $record): string {
                        $conflicts = $record->metadata['field_conflicts'] ?? null;
                        if (empty($conflicts)) {
                            return '';
                        }

                        $labels = array_map(fn (string $f) => match ($f) {
                            'full_name'   => 'Name',
                            'emirate'     => 'Emirate',
                            'nationality' => 'Nationality',
                            'gender'      => 'Gender',
                            'interest'    => 'Interest',
                            'country_iso' => 'Country',
                            default       => $f,
                        }, array_keys($conflicts));

                        return implode(', ', $labels);
                    })
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),

                TextColumn::make('client.full_name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—')
                    ->description(fn (ClientSource $record): ?string =>
                        filled($record->metadata['raw_name'] ?? null) &&
                        ($record->metadata['raw_name'] !== $record->client?->full_name)
                            ? 'Imported as: ' . $record->metadata['raw_name']
                            : null
                    )
                    ->url(fn (ClientSource $record): ?string =>
                        $record->client_id
                            ? ClientResource::getUrl('edit', ['record' => $record->client_id])
                            : null
                    )
                    ->openUrlInNewTab(),

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

                SelectFilter::make('has_conflicts')
                    ->label('Has Conflicts')
                    ->options(['yes' => 'Has conflicts'])
                    ->query(fn (Builder $query, array $data): Builder =>
                        ($data['value'] ?? null) === 'yes'
                            ? $query->whereRaw("metadata->>'field_conflicts' IS NOT NULL")
                            : $query
                    ),

                SelectFilter::make('emirate')
                    ->label('Emirate')
                    ->options([
                        'Dubai'          => 'Dubai',
                        'Abu Dhabi'      => 'Abu Dhabi',
                        'Sharjah'        => 'Sharjah',
                        'Ajman'          => 'Ajman',
                        'Ras Al Khaimah' => 'Ras Al Khaimah',
                        'Fujairah'       => 'Fujairah',
                        'Umm Al Quwain'  => 'Umm Al Quwain',
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $data['value']
                            ? $query->whereHas('client', fn (Builder $q) => $q->where('emirate', $data['value']))
                            : $query
                    ),

                SelectFilter::make('tier')
                    ->label('Tier')
                    ->options([
                        'vip'            => 'VIP',
                        'high_net_worth' => 'High Net Worth',
                        'premium'        => 'Premium',
                        'standard'       => 'Standard',
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $data['value']
                            ? $query->whereHas('client', fn (Builder $q) => $q->where('tier', $data['value']))
                            : $query
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->recordAction(null)
            ->recordUrl(null)
            ->recordActions([
                Action::make('apply_conflicts')
                    ->label('Apply imported values')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (ClientSource $record): bool =>
                        ! empty($record->metadata['field_conflicts'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Apply imported values to this contact?')
                    ->modalDescription(function (ClientSource $record): string {
                        $conflicts = $record->metadata['field_conflicts'] ?? [];
                        $lines = [];
                        foreach ($conflicts as $field => $diff) {
                            $label = match ($field) {
                                'full_name'   => 'Name',
                                'emirate'     => 'Emirate',
                                'nationality' => 'Nationality',
                                'gender'      => 'Gender',
                                'interest'    => 'Interest',
                                'country_iso' => 'Country',
                                default       => $field,
                            };
                            $lines[] = $label . ': "' . $diff['stored'] . '" → "' . $diff['imported'] . '"';
                        }
                        return 'The following stored values will be replaced: ' . implode('; ', $lines) . '.';
                    })
                    ->modalSubmitActionLabel('Apply')
                    ->action(function (ClientSource $record): void {
                        $conflicts = $record->metadata['field_conflicts'] ?? [];
                        if (empty($conflicts) || ! $record->client_id) {
                            return;
                        }

                        $updates = [];
                        foreach ($conflicts as $field => $diff) {
                            $updates[$field] = $diff['imported'];
                        }

                        Client::where('id', $record->client_id)->update($updates);

                        Notification::make()
                            ->success()
                            ->title('Contact updated')
                            ->send();
                    }),

                Action::make('view_contact')
                    ->label('View contact')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (ClientSource $record): ?string =>
                        $record->client_id
                            ? ClientResource::getUrl('edit', ['record' => $record->client_id])
                            : null
                    )
                    ->openUrlInNewTab(),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
