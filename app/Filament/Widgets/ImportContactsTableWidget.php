<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Modules\IVR\Models\IvrImport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ImportContactsTableWidget extends TableWidget
{
    public int $importId;

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        $import = IvrImport::find($this->importId);
        $total  = $import ? number_format((int) $import->successful_rows) : '—';
        return "Imported Contacts ({$total})";
    }

    public function table(Table $table): Table
    {
        $importId = $this->importId;

        return $table
            ->query(
                Client::query()
                    ->select([
                        'clients.*',
                        DB::raw("(
                            SELECT metadata->>'duplicate'
                            FROM client_sources cs
                            WHERE cs.client_id = clients.id
                              AND cs.source_reference = '" . (int) $importId . "'
                              AND cs.source_type = 'raw_import'
                            LIMIT 1
                        ) as _is_duplicate"),
                    ])
                    ->whereHas('sources', fn (Builder $q) => $q
                        ->where('source_reference', (string) $importId)
                        ->where('source_type', 'raw_import')
                    )
                    ->with(['primaryPhone', 'primaryEmail'])
            )
            ->columns([
                TextColumn::make('_is_duplicate')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'true' ? 'gray' : 'success')
                    ->formatStateUsing(fn (?string $state) => $state === 'true' ? 'Duplicate' : 'New'),

                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('primaryPhone.normalized_phone')
                    ->label('Phone')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('primaryEmail.email')
                    ->label('Email')
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('emirate')
                    ->label('Emirate')
                    ->badge()
                    ->placeholder('—'),

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
                        default          => 'gray',
                    }),

                TextColumn::make('completeness_score')
                    ->label('Completeness')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? $state . '%' : '—')
                    ->sortable()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 75   => 'success',
                        $state >= 50   => 'warning',
                        default        => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['new' => 'New contacts', 'duplicate' => 'Duplicates'])
                    ->query(fn (Builder $query, array $data): Builder =>
                        match ($data['value'] ?? null) {
                            'new'       => $query->whereRaw("(
                                SELECT metadata->>'duplicate'
                                FROM client_sources cs
                                WHERE cs.client_id = clients.id
                                  AND cs.source_reference = '" . (int) $importId . "'
                                  AND cs.source_type = 'raw_import'
                                LIMIT 1
                            ) = 'false' OR (
                                SELECT metadata->>'duplicate'
                                FROM client_sources cs
                                WHERE cs.client_id = clients.id
                                  AND cs.source_reference = '" . (int) $importId . "'
                                  AND cs.source_type = 'raw_import'
                                LIMIT 1
                            ) IS NULL"),
                            'duplicate' => $query->whereRaw("(
                                SELECT metadata->>'duplicate'
                                FROM client_sources cs
                                WHERE cs.client_id = clients.id
                                  AND cs.source_reference = '" . (int) $importId . "'
                                  AND cs.source_type = 'raw_import'
                                LIMIT 1
                            ) = 'true'"),
                            default     => $query,
                        }
                    ),

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

                SelectFilter::make('tier')
                    ->options([
                        'vip'            => 'VIP',
                        'high_net_worth' => 'High Net Worth',
                        'premium'        => 'Premium',
                        'standard'       => 'Standard',
                    ]),
            ])
            ->defaultSort('clients.id', 'desc')
            ->paginated([25, 50, 100])
            ->recordAction(null)
            ->recordUrl(null)
            ->recordActions([])
            ->toolbarActions([]);
    }
}
