<?php

namespace App\Modules\IVR\Jobs;

use App\Jobs\RecomputeClientScoresJob;
use App\Models\Client;
use App\Models\ClientSource;
use App\Models\ImportStaging;
use App\Models\Ownership;
use App\Support\NameNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromoteStagingContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    private const CHUNK_SIZE = 200;

    public function __construct(public readonly ?string $batchId = null) {}

    public function handle(): void
    {
        $query = ImportStaging::query()
            ->where('status', ImportStaging::STATUS_NEEDS_REVIEW)
            ->when($this->batchId, fn ($q) => $q->where('batch_id', $this->batchId));

        $total = $query->count();
        $promoted = 0;
        $promotedClientIds = [];

        Log::channel('ivr')->info('Starting staging promotion.', [
            'batch_id' => $this->batchId,
            'total' => $total,
        ]);

        $query->chunkById(self::CHUNK_SIZE, function ($rows) use (&$promoted, &$promotedClientIds): void {
            DB::transaction(function () use ($rows, &$promoted, &$promotedClientIds): void {
                $sourceRows = [];
                $now = now()->toDateTimeString();

                foreach ($rows as $staged) {
                    $client = Client::firstOrCreate(
                        array_filter([
                            'full_name' => $staged->name ? NameNormalizer::normalize($staged->name) : null,
                            'emirate'   => $staged->emirate ?: null,
                        ]),
                        ['country_iso' => $staged->country_iso ?: null],
                    );

                    if ($staged->marketing_area_id && $staged->emirate) {
                        $matchFields = [
                            'client_id'         => $client->id,
                            'emirate'           => $staged->emirate,
                            'marketing_area_id' => $staged->marketing_area_id,
                            'project_id'        => $staged->project_id,
                            'building_id'       => $staged->building_id,
                            'unit_reference'    => $staged->raw_unit_reference ?: null,
                            'relationship_type' => $staged->relationship_type ?: 'owner',
                        ];

                        $existing = Ownership::where($matchFields)->first();

                        if ($existing) {
                            $sourceNames = $existing->source_names ?? [];
                            if ($staged->source && ! in_array($staged->source, $sourceNames, true)) {
                                $sourceNames[] = $staged->source;
                            }

                            $existing->fill([
                                'official_area_id' => $staged->official_area_id,
                                'confidence_level' => Ownership::higherConfidence($existing->confidence_level, $staged->confidence_level),
                                'last_source_name' => $staged->source,
                                'source_names'     => $sourceNames,
                            ])->save();
                        } else {
                            Ownership::create(array_merge($matchFields, [
                                'official_area_id'  => $staged->official_area_id,
                                'confidence_level'  => $staged->confidence_level,
                                'last_source_name'  => $staged->source,
                                'source_names'      => $staged->source ? [$staged->source] : [],
                                'first_confirmed_at' => $now,
                            ]));
                        }
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
                            'raw_name'             => $staged->name,
                            'raw_emirate'          => $staged->emirate,
                            'raw_marketing_area'   => $staged->raw_marketing_area,
                            'raw_project'          => $staged->raw_project_name,
                            'raw_building'         => $staged->raw_building_name,
                            'raw_unit'             => $staged->raw_unit_reference,
                            'raw_relationship_type' => $staged->relationship_type,
                        ]),
                        'created_at'             => $now,
                        'updated_at'             => $now,
                    ];

                    $staged->update(['status' => ImportStaging::STATUS_MATCHED]);
                    $promotedClientIds[] = $client->id;
                    $promoted++;
                }

                if ($sourceRows !== []) {
                    DB::table('client_sources')->insertOrIgnore($sourceRows);
                }
            });
        });

        if ($promotedClientIds !== []) {
            RecomputeClientScoresJob::dispatch(array_unique($promotedClientIds))->onQueue('analysis');
        }

        Log::channel('ivr')->info('Staging promotion complete.', [
            'batch_id' => $this->batchId,
            'promoted' => $promoted,
        ]);
    }
}
