<?php

namespace App\Modules\IVR\Support;

use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Modules\IVR\Models\IvrImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RawImportReverter
{
    public function revert(IvrImport $import, ?int $userId = null, ?string $reason = null): void
    {
        $affectedPhoneCount = 0;
        $deletedPhoneCount = 0;
        $deletedClientCount = 0;

        try {
            DB::transaction(function () use ($import, $userId, $reason, &$affectedPhoneCount, &$deletedPhoneCount, &$deletedClientCount): void {
                $affectedPhoneIds = $this->rawImportSourceQuery($import)
                    ->whereNotNull('client_phone_number_id')
                    ->distinct()
                    ->pluck('client_phone_number_id')
                    ->values();

                $affectedPhoneCount = $affectedPhoneIds->count();

                $affectedClientIds = ClientPhoneNumber::query()
                    ->whereIn('id', $affectedPhoneIds)
                    ->whereNotNull('client_id')
                    ->distinct()
                    ->pluck('client_id')
                    ->values();

                $this->rawImportSourceQuery($import)->delete();

                $deletablePhoneIds = ClientPhoneNumber::query()
                    ->whereIn('id', $affectedPhoneIds)
                    ->whereDoesntHave('sources')
                    ->whereDoesntHave('suppressions')
                    ->whereDoesntHave('ivrCallRecords')
                    ->pluck('id')
                    ->values();

                $deletedPhoneCount = $deletablePhoneIds->count();

                $deletablePhoneIds
                    ->chunk(1000)
                    ->each(function ($phoneIds): void {
                        ClientPhoneNumber::query()
                            ->whereIn('id', $phoneIds)
                            ->delete();
                    });

                $this->refreshLastRawSourceForPhones($affectedPhoneIds->diff($deletablePhoneIds)->values());

                $deletedClientCount = $this->deleteOrphanedClients($affectedClientIds);

                $import->update([
                    'status' => 'reverted',
                    'reverted_at' => now(),
                    'reverted_by' => $userId,
                    'revert_reason' => $reason,
                    'error_message' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $import->forceFill([
                'status' => 'revert_failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        Log::channel('ivr')->info('Reverted raw IVR import.', [
            'import_id' => $import->id,
            'file_name' => $import->original_file_name,
            'affected_phone_numbers' => $affectedPhoneCount,
            'deleted_phone_numbers' => $deletedPhoneCount,
            'deleted_clients' => $deletedClientCount,
        ]);
    }

    private function rawImportSourceQuery(IvrImport $import): Builder
    {
        return ClientSource::query()
            ->where('channel', 'ivr')
            ->where('source_type', 'raw_import')
            ->where('source_reference', (string) $import->id);
    }

    private function refreshLastRawSourceForPhones($phoneIds): void
    {
        $phoneIds
            ->chunk(1000)
            ->each(function ($chunkedPhoneIds): void {
                ClientPhoneNumber::query()
                    ->whereIn('id', $chunkedPhoneIds)
                    ->update([
                        'last_source_name' => $this->latestRawSourceNameSubquery(),
                        'last_imported_at' => $this->latestRawSourceCreatedAtSubquery(),
                        'updated_at' => now(),
                    ]);
            });
    }

    private function latestRawSourceNameSubquery(): QueryBuilder
    {
        return DB::table('client_sources')
            ->select('source_name')
            ->whereColumn('client_sources.client_phone_number_id', 'client_phone_numbers.id')
            ->where('channel', 'ivr')
            ->where('source_type', 'raw_import')
            ->latest()
            ->limit(1);
    }

    private function latestRawSourceCreatedAtSubquery(): QueryBuilder
    {
        return DB::table('client_sources')
            ->select('created_at')
            ->whereColumn('client_sources.client_phone_number_id', 'client_phone_numbers.id')
            ->where('channel', 'ivr')
            ->where('source_type', 'raw_import')
            ->latest()
            ->limit(1);
    }

    private function deleteOrphanedClients($clientIds): int
    {
        $deletedClientCount = 0;

        $clientIds
            ->chunk(1000)
            ->each(function ($chunkedClientIds) use (&$deletedClientCount): void {
                $orphanedClientIds = DB::table('clients')
                    ->whereIn('id', $chunkedClientIds)
                    ->whereNotExists(function (QueryBuilder $query): void {
                        $query->selectRaw('1')
                            ->from('client_phone_numbers')
                            ->whereColumn('client_phone_numbers.client_id', 'clients.id');
                    })
                    ->whereNotExists(function (QueryBuilder $query): void {
                        $query->selectRaw('1')
                            ->from('client_sources')
                            ->whereColumn('client_sources.client_id', 'clients.id');
                    })
                    ->pluck('id');

                $deletedClientCount += $orphanedClientIds->count();

                if ($orphanedClientIds->isNotEmpty()) {
                    DB::table('clients')
                        ->whereIn('id', $orphanedClientIds)
                        ->delete();
                }
            });

        return $deletedClientCount;
    }
}
