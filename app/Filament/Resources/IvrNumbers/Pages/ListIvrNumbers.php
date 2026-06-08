<?php

namespace App\Filament\Resources\IvrNumbers\Pages;

use App\Filament\Resources\IvrNumbers\IvrNumberResource;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\Tag;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListIvrNumbers extends ListRecords
{
    protected static string $resource = IvrNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Select::make('tag_id')
                        ->label('Tag filter (optional)')
                        ->placeholder('— All contacts —')
                        ->options(fn () => Tag::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    Select::make('limit')
                        ->label('Export limit')
                        ->options([
                            '500'  => '500 numbers',
                            '1000' => '1,000 numbers',
                            '2000' => '2,000 numbers',
                            '5000' => '5,000 numbers',
                            'all'  => 'All eligible numbers',
                        ])
                        ->default('1000')
                        ->required(),
                ])
                ->action(function (array $data): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $limit = $data['limit'] === 'all' ? null : (int) $data['limit'];
                    $tagId = $data['tag_id'] ?? null;

                    $query = ClientPhoneNumber::query()
                        ->where('is_uae', true)
                        ->whereHas('client', fn ($q) => $q->whereNotNull('full_name')->whereRaw("trim(full_name) <> ''"))
                        ->whereDoesntHave('suppressions', fn ($q) =>
                            $q->whereNull('released_at')->where(fn ($q) =>
                                $q->whereNull('channel')->orWhere('channel', 'ivr')
                            )
                        )
                        ->whereNull('unsubscribed_at')
                        ->with(['client.primaryEmail', 'ivrProfile'])
                        ->where(fn ($q) =>
                            $q->whereDoesntHave('ivrProfile')
                              ->orWhereHas('ivrProfile', fn ($p) =>
                                  $p->where('usage_status', 'active')
                                    ->where(fn ($p) =>
                                        $p->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', now())
                                    )
                              )
                        )
                        ->when($tagId, fn ($q) =>
                            $q->whereHas('client.tags', fn ($t) => $t->where('tags.id', $tagId))
                        )
                        ->orderByDesc('is_primary')
                        ->orderBy('id');

                    if ($limit) {
                        $query->limit($limit);
                    }

                    $numbers = $query->get();
                    $tagName = $tagId ? Tag::find($tagId)?->name : null;
                    $fileName = 'ivr_numbers_' . ($tagName ? strtolower(str_replace(' ', '_', $tagName)) . '_' : '') . now()->format('Y-m-d') . '.csv';

                    return response()->streamDownload(function () use ($numbers): void {
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['Phone', 'Name', 'Email', 'Emirate', 'Tags', 'IVR Status', 'Last Called', 'Cooldown Until']);

                        foreach ($numbers as $number) {
                            fputcsv($handle, [
                                $number->normalized_phone,
                                $number->client?->full_name,
                                $number->client?->primaryEmail?->email,
                                $number->client?->emirate,
                                $number->client?->tags->pluck('name')->implode(', '),
                                $number->ivrProfile?->usage_status ?? 'active',
                                $number->ivrProfile?->last_called_at?->format('Y-m-d'),
                                $number->ivrProfile?->cooldown_until?->format('Y-m-d'),
                            ]);
                        }

                        fclose($handle);
                    }, $fileName, ['Content-Type' => 'text/csv']);
                })
                ->modalHeading('Export IVR Numbers')
                ->modalDescription('Downloads active, eligible IVR numbers (not suppressed, not in cooldown). Filter by tag to export only owners, leads, etc.'),

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
}
