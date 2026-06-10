<?php

namespace App\Filament\Resources\IvrNumbers\Pages;

use App\Filament\Resources\IvrNumbers\IvrNumberResource;
use App\Filament\Widgets\IvrNumberStatsWidget;
use App\Models\Client;
use App\Models\Tag;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListIvrNumbers extends ListRecords
{
    protected static string $resource = IvrNumberResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            IvrNumberStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Filtered CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    TextInput::make('limit')
                        ->label('Number of records to export')
                        ->placeholder('Leave empty to export all')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Exports are randomised when a limit is set so you get a varied sample.'),
                ])
                ->action(function (array $data): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $limit = filled($data['limit'] ?? null) ? (int) $data['limit'] : null;
                    // Always start from the resource's scoped base query so the UAE
                    // mobile filter is guaranteed regardless of table state.
                    $query = IvrNumberResource::getEloquentQuery();

                    // Explicitly re-apply each user-facing filter from Livewire state.
                    // We do NOT use getFilteredTableQuery() here because if the table's
                    // query closure is unavailable the fallback would silently export
                    // unfiltered data. Reading tableFilters directly is deterministic.
                    $filters = $this->tableFilters ?? [];

                    $emirate = $filters['emirate']['value'] ?? null;
                    if (filled($emirate)) {
                        $query->whereExists(fn ($q) => $q
                            ->selectRaw('1')
                            ->from('clients')
                            ->whereColumn('clients.id', 'client_phone_numbers.client_id')
                            ->where('clients.emirate', $emirate)
                        );
                    }

                    $communityIds = $filters['communities']['values'] ?? [];
                    if (filled($communityIds)) {
                        $query->whereExists(fn ($q) => $q
                            ->selectRaw('1')
                            ->from('ownerships')
                            ->whereColumn('ownerships.client_id', 'client_phone_numbers.client_id')
                            ->whereIn('ownerships.marketing_area_id', $communityIds)
                        );
                    }

                    $tagIds = $filters['tags']['values'] ?? [];
                    if (filled($tagIds)) {
                        $query->whereExists(fn ($q) => $q
                            ->selectRaw('1')
                            ->from('client_tags')
                            ->whereColumn('client_tags.client_id', 'client_phone_numbers.client_id')
                            ->whereIn('client_tags.tag_id', $tagIds)
                        );
                    }

                    $relationshipTypes = $filters['relationship_types']['values'] ?? [];
                    if (filled($relationshipTypes)) {
                        $query->whereExists(fn ($q) => $q
                            ->selectRaw('1')
                            ->from('ownerships')
                            ->whereColumn('ownerships.client_id', 'client_phone_numbers.client_id')
                            ->whereIn('ownerships.relationship_type', $relationshipTypes)
                        );
                    }

                    $query = self::activeExportQuery($query);

                    if ($limit) {
                        // PostgreSQL forbids ORDER BY RANDOM() on a SELECT DISTINCT query
                        // unless RANDOM() is in the select list. Wrap as a subquery so the
                        // outer query can safely apply random ordering + limit.
                        $query = DB::query()
                            ->fromSub($query->reorder(), 'filtered')
                            ->select('normalized_phone')
                            ->inRandomOrder()
                            ->limit($limit);
                    }

                    $limitSuffix = $limit ? "_limit{$limit}" : '';
                    $fileName = 'ivr_filtered_numbers_'.now()->format('Y-m-d').$limitSuffix.'.csv';

                    return response()->streamDownload(function () use ($query): void {
                        set_time_limit(0);

                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['phone_number']);

                        foreach ($query->cursor() as $number) {
                            fputcsv($handle, [$number->normalized_phone]);
                        }

                        fclose($handle);
                    }, $fileName, ['Content-Type' => 'text/csv']);
                })
                ->modalHeading('Export IVR Numbers')
                ->modalDescription('Exports active, eligible phone numbers matching the current filters. Optionally cap the export to a specific count — a random sample will be taken.'),

            Action::make('bulk_tag_leads')
                ->label('Tag Untagged as Lead')
                ->icon('heroicon-o-tag')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Tag all untagged contacts as Lead?')
                ->modalDescription('Every contact in the database that has no tag will receive the "Lead" tag. This is safe to run multiple times — already-tagged contacts are not affected.')
                ->action(function (): void {
                    $leadTag = Tag::firstOrCreate(['name' => 'Lead']);

                    // Find all clients with no tags at all, in batches
                    $tagged = 0;

                    Client::query()
                        ->whereDoesntHave('tags')
                        ->select('id')
                        ->chunkById(500, function ($clients) use ($leadTag, &$tagged): void {
                            $ids = $clients->pluck('id')->all();

                            DB::table('client_tags')->insertOrIgnore(
                                collect($ids)->map(fn ($id) => [
                                    'client_id'  => $id,
                                    'tag_id'     => $leadTag->id,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ])->all()
                            );

                            $tagged += count($ids);
                        });

                    Notification::make()
                        ->title("Tagged {$tagged} contact(s) as Lead.")
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function activeExportQuery(Builder $query): Builder
    {
        return $query
            ->where('client_phone_numbers.is_uae', true)
            ->where('client_phone_numbers.normalized_phone', 'like', '+9715%')
            ->whereRaw('LENGTH(client_phone_numbers.normalized_phone) = 13')
            ->whereNotNull('client_phone_numbers.normalized_phone')
            ->whereHas('client', fn (Builder $client): Builder =>
                $client->whereNotNull('full_name')->whereRaw("trim(full_name) <> ''")
            )
            ->whereDoesntHave('suppressions', fn (Builder $q) => $q->activeIvr())
            ->whereNull('client_phone_numbers.unsubscribed_at')
            ->where(fn (Builder $profileQuery): Builder =>
                $profileQuery->whereDoesntHave('ivrProfile')
                    ->orWhereHas('ivrProfile', fn (Builder $profile): Builder =>
                        $profile->where('usage_status', 'active')
                            ->where(fn (Builder $cooldown): Builder =>
                                $cooldown->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', now())
                            )
                    )
            )
            ->select('client_phone_numbers.normalized_phone')
            ->distinct()
            ->reorder('client_phone_numbers.normalized_phone');
    }
}
