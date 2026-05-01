<?php

namespace App\Modules\IVR\Support;

use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Modules\IVR\Models\IvrImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RawImportDeleter
{
    public function delete(IvrImport $import, ?int $userId = null, ?string $reason = null): void
    {
        if ($import->reverted_at !== null || $import->status === 'deleted') {
            return;
        }

        $affectedPhoneCount = 0;
        $deletedPhoneCount = 0;
        $deletedClientCount = 0;

        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                [$affectedPhoneCount, $deletedPhoneCount, $deletedClientCount] = $this->deleteWithEloquentFallback($import, $userId, $reason);

                $this->logDelete($import, $affectedPhoneCount, $deletedPhoneCount, $deletedClientCount);

                return;
            }

            DB::transaction(function () use ($import, $userId, $reason, &$affectedPhoneCount, &$deletedPhoneCount, &$deletedClientCount): void {
                $now = now();

                DB::statement('create temporary table raw_import_delete_phone_ids (id bigint primary key, client_id bigint) on commit drop');
                DB::statement('create temporary table raw_import_delete_deletable_phone_ids (id bigint primary key) on commit drop');
                DB::statement('create temporary table raw_import_delete_client_ids (id bigint primary key) on commit drop');

                DB::insert(
                    <<<'SQL'
                    insert into raw_import_delete_phone_ids (id, client_id)
                    select distinct cpn.id, cpn.client_id
                    from client_sources cs
                    inner join client_phone_numbers cpn on cpn.id = cs.client_phone_number_id
                    where cs.channel = ?
                      and cs.source_type = ?
                      and cs.source_reference = ?
                      and cs.client_phone_number_id is not null
                    SQL,
                    ['ivr', 'raw_import', (string) $import->id],
                );

                $affectedPhoneCount = (int) DB::table('raw_import_delete_phone_ids')->count();

                DB::insert(
                    <<<'SQL'
                    insert into raw_import_delete_client_ids (id)
                    select distinct client_id
                    from raw_import_delete_phone_ids
                    where client_id is not null
                    SQL,
                );

                DB::insert(
                    <<<'SQL'
                    insert into raw_import_delete_deletable_phone_ids (id)
                    select p.id
                    from raw_import_delete_phone_ids p
                    where not exists (
                        select 1
                        from client_sources cs
                        where cs.client_phone_number_id = p.id
                          and not (
                            cs.channel = ?
                            and cs.source_type = ?
                            and cs.source_reference = ?
                          )
                    )
                    and not exists (
                        select 1
                        from contact_suppressions sup
                        where sup.client_phone_number_id = p.id
                    )
                    and not exists (
                        select 1
                        from ivr_call_records calls
                        where calls.client_phone_number_id = p.id
                    )
                    SQL,
                    ['ivr', 'raw_import', (string) $import->id],
                );

                $deletedPhoneCount = (int) DB::table('raw_import_delete_deletable_phone_ids')->count();

                DB::delete(
                    <<<'SQL'
                    delete from client_sources
                    where channel = ?
                      and source_type = ?
                      and source_reference = ?
                    SQL,
                    ['ivr', 'raw_import', (string) $import->id],
                );

                DB::delete(
                    <<<'SQL'
                    delete from client_phone_numbers
                    where id in (
                        select id
                        from raw_import_delete_deletable_phone_ids
                    )
                    SQL,
                );

                DB::update(
                    <<<'SQL'
                    update client_phone_numbers cpn
                    set last_source_name = (
                            select cs.source_name
                            from client_sources cs
                            where cs.client_phone_number_id = cpn.id
                              and cs.channel = ?
                              and cs.source_type = ?
                            order by cs.created_at desc, cs.id desc
                            limit 1
                        ),
                        last_imported_at = (
                            select cs.created_at
                            from client_sources cs
                            where cs.client_phone_number_id = cpn.id
                              and cs.channel = ?
                              and cs.source_type = ?
                            order by cs.created_at desc, cs.id desc
                            limit 1
                        ),
                        updated_at = ?
                    where exists (
                        select 1
                        from raw_import_delete_phone_ids p
                        where p.id = cpn.id
                    )
                    and not exists (
                        select 1
                        from raw_import_delete_deletable_phone_ids d
                        where d.id = cpn.id
                    )
                    SQL,
                    ['ivr', 'raw_import', 'ivr', 'raw_import', $now],
                );

                $deletedClientCount = DB::delete(
                    <<<'SQL'
                    delete from clients c
                    where exists (
                        select 1
                        from raw_import_delete_client_ids ids
                        where ids.id = c.id
                    )
                    and not exists (
                        select 1
                        from client_phone_numbers cpn
                        where cpn.client_id = c.id
                    )
                    and not exists (
                        select 1
                        from client_sources cs
                        where cs.client_id = c.id
                    )
                    SQL,
                );

                $import->update([
                    'status' => 'deleted',
                    'reverted_at' => $now,
                    'reverted_by' => $userId,
                    'revert_reason' => $reason,
                    'error_message' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $import->forceFill([
                'status' => 'delete_failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        $this->logDelete($import, $affectedPhoneCount, $deletedPhoneCount, $deletedClientCount);
    }

    private function deleteWithEloquentFallback(IvrImport $import, ?int $userId, ?string $reason): array
    {
        $affectedPhoneCount = 0;
        $deletedPhoneCount = 0;
        $deletedClientCount = 0;

        DB::transaction(function () use ($import, $userId, $reason, &$affectedPhoneCount, &$deletedPhoneCount, &$deletedClientCount): void {
            $affectedPhoneIds = ClientSource::query()
                ->where('channel', 'ivr')
                ->where('source_type', 'raw_import')
                ->where('source_reference', (string) $import->id)
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

            ClientSource::query()
                ->where('channel', 'ivr')
                ->where('source_type', 'raw_import')
                ->where('source_reference', (string) $import->id)
                ->delete();

            $deletablePhoneIds = ClientPhoneNumber::query()
                ->whereIn('id', $affectedPhoneIds)
                ->whereDoesntHave('sources')
                ->whereDoesntHave('suppressions')
                ->whereDoesntHave('ivrCallRecords')
                ->pluck('id')
                ->values();

            $deletedPhoneCount = $deletablePhoneIds->count();

            $deletablePhoneIds
                ->chunk(500)
                ->each(function ($phoneIds): void {
                    ClientPhoneNumber::query()
                        ->whereIn('id', $phoneIds)
                        ->delete();
                });

            $affectedPhoneIds
                ->diff($deletablePhoneIds)
                ->chunk(500)
                ->each(function ($phoneIds): void {
                    ClientPhoneNumber::query()
                        ->whereIn('id', $phoneIds)
                        ->get()
                        ->each(function (ClientPhoneNumber $phoneNumber): void {
                            $latestRawSource = $phoneNumber->sources()
                                ->where('channel', 'ivr')
                                ->where('source_type', 'raw_import')
                                ->latest()
                                ->first();

                            $phoneNumber->forceFill([
                                'last_source_name' => $latestRawSource?->source_name,
                                'last_imported_at' => $latestRawSource?->created_at,
                            ])->save();
                        });
                });

            $affectedClientIds
                ->chunk(500)
                ->each(function ($clientIds) use (&$deletedClientCount): void {
                    $orphanedClientIds = DB::table('clients')
                        ->whereIn('id', $clientIds)
                        ->whereNotExists(function ($query): void {
                            $query->selectRaw('1')
                                ->from('client_phone_numbers')
                                ->whereColumn('client_phone_numbers.client_id', 'clients.id');
                        })
                        ->whereNotExists(function ($query): void {
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

            $import->update([
                'status' => 'deleted',
                'reverted_at' => now(),
                'reverted_by' => $userId,
                'revert_reason' => $reason,
                'error_message' => null,
            ]);
        });

        return [$affectedPhoneCount, $deletedPhoneCount, $deletedClientCount];
    }

    private function logDelete(IvrImport $import, int $affectedPhoneCount, int $deletedPhoneCount, int $deletedClientCount): void
    {
        Log::channel('ivr')->info('Deleted raw IVR import.', [
            'import_id' => $import->id,
            'file_name' => $import->original_file_name,
            'affected_phone_numbers' => $affectedPhoneCount,
            'deleted_phone_numbers' => $deletedPhoneCount,
            'deleted_clients' => $deletedClientCount,
        ]);
    }
}
