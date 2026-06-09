<?php

namespace App\Filament\Resources\ImportStagings\Tables;

use App\Models\Client;
use App\Models\ClientSource;
use App\Models\ImportStaging;
use App\Models\Ownership;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ImportStagingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_id')
                    ->label('Batch')
                    ->formatStateUsing(fn (string $state) => self::batchLabel($state))
                    ->copyable()
                    ->copyableState(fn (string $state) => $state)
                    ->tooltip(fn (string $state) => $state),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'matched'      => 'success',
                        'needs_review' => 'warning',
                        'rejected'     => 'danger',
                        default        => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state)))
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('emirate')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('raw_marketing_area')
                    ->label('Marketing Area (raw)')
                    ->placeholder('—'),

                TextColumn::make('marketingArea.name')
                    ->label('Resolved Area')
                    ->placeholder('—')
                    ->color('success'),

                TextColumn::make('raw_project_name')
                    ->label('Project (raw)')
                    ->placeholder('—'),

                TextColumn::make('status_reason')
                    ->label('Issue')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn (?string $state) => $state),

                TextColumn::make('created_at')
                    ->label('Staged')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'      => 'Pending',
                        'matched'      => 'Matched',
                        'needs_review' => 'Needs Review',
                        'rejected'     => 'Rejected',
                    ]),

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

                SelectFilter::make('batch_id')
                    ->label('Batch / Import')
                    ->options(fn () =>
                        // GROUP BY + MAX avoids the PostgreSQL DISTINCT+ORDER BY restriction
                        ImportStaging::select('batch_id', DB::raw('max(created_at) as latest_at'))
                            ->groupBy('batch_id')
                            ->orderByDesc('latest_at')
                            ->pluck('batch_id', 'batch_id')
                            ->mapWithKeys(fn ($id) => [$id => self::batchLabel($id)])
                            ->all()
                    )
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([DeleteAction::make()])
            ->bulkActions([
                BulkAction::make('promote_selected')
                    ->label('Create Contacts')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Create Contacts from Selected Rows')
                    ->modalDescription('Creates a client + ownership record for each selected staged row. Rows already matched are skipped.')
                    ->modalSubmitActionLabel('Create Contacts')
                    ->action(function (Collection $records): void {
                        $promoted = 0;
                        $skipped  = 0;

                        DB::transaction(function () use ($records, &$promoted, &$skipped): void {
                            $sourceRows = [];
                            $now = now()->toDateTimeString();

                            foreach ($records as $staged) {
                                if ($staged->status === ImportStaging::STATUS_MATCHED) {
                                    $skipped++;
                                    continue;
                                }

                                $client = Client::firstOrCreate(
                                    array_filter([
                                        'full_name' => $staged->name ?: null,
                                        'emirate'   => $staged->emirate ?: null,
                                    ]),
                                    ['country_iso' => $staged->country_iso ?: null],
                                );

                                if ($staged->marketing_area_id && $staged->emirate) {
                                    Ownership::updateOrCreate(
                                        [
                                            'client_id'         => $client->id,
                                            'emirate'           => $staged->emirate,
                                            'marketing_area_id' => $staged->marketing_area_id,
                                            'project_id'        => $staged->project_id,
                                            'building_id'       => $staged->building_id,
                                            'unit_reference'    => $staged->raw_unit_reference ?: null,
                                            'relationship_type' => $staged->relationship_type ?: 'owner',
                                        ],
                                        [
                                            'official_area_id' => $staged->official_area_id,
                                            'confidence_level' => $staged->confidence_level,
                                            'source'           => $staged->source,
                                        ],
                                    );
                                }

                                $sourceRows[] = [
                                    'client_id'              => $client->id,
                                    'client_phone_number_id' => null,
                                    'channel'                => 'ivr',
                                    'source_type'            => 'staging_promoted',
                                    'source_name'            => $staged->source,
                                    'source_file_name'       => null,
                                    'source_reference'       => $staged->batch_id,
                                    'metadata'               => json_encode([
                                        'raw_name'              => $staged->name,
                                        'raw_emirate'           => $staged->emirate,
                                        'raw_marketing_area'    => $staged->raw_marketing_area,
                                        'raw_project'           => $staged->raw_project_name,
                                        'raw_building'          => $staged->raw_building_name,
                                        'raw_unit'              => $staged->raw_unit_reference,
                                        'raw_relationship_type' => $staged->relationship_type,
                                    ]),
                                    'created_at'             => $now,
                                    'updated_at'             => $now,
                                ];

                                $staged->update(['status' => ImportStaging::STATUS_MATCHED]);
                                $promoted++;
                            }

                            if ($sourceRows !== []) {
                                DB::table('client_sources')->insertOrIgnore($sourceRows);
                            }
                        });

                        Notification::make()
                            ->success()
                            ->title("{$promoted} contact(s) created" . ($skipped ? ", {$skipped} already matched skipped" : ''))
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    private static function batchLabel(string $batchId): string
    {
        // "raw-import-42" → "Import #42"
        if (preg_match('/^raw-import-(\d+)$/', $batchId, $m)) {
            return 'Import #'.$m[1];
        }

        return substr($batchId, 0, 12).(strlen($batchId) > 12 ? '…' : '');
    }
}
