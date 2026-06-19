<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ImportReviewQueue;
use App\Models\ImportStaging;
use App\Modules\IVR\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Promotes a reviewed import-staging row into a Client + Ownership.
 *
 * The bulk row-staging / auto-promotion methods (processRow, promoteMatched and their helpers)
 * were removed as dead code: live raw-contact imports go through RawImportProcessor, and only the
 * review-queue approval flow uses this class (the Filament Import Review Queue table calls
 * promoteReviewItem after a human confirms the resolved location IDs).
 */
class ImportStagingProcessor
{
    /**
     * Promote a single review-queue item into a contact + ownership.
     * Called from the Filament approve action after the reviewer confirms the IDs.
     *
     * @param  array{
     *   marketing_area_id: int,
     *   official_area_id: int|null,
     *   project_id: int|null,
     *   building_id: int|null,
     * }  $confirmedIds
     */
    public function promoteReviewItem(
        ImportReviewQueue $reviewItem,
        array $confirmedIds,
        PhoneNormalizer $phoneNormalizer,
    ): Client {
        $staging = $reviewItem->staging;

        $enricher = app(RawContactImportEnricher::class);

        $normalizedPhone = null;
        if ($staging->phone) {
            try {
                $normalizedPhone = $phoneNormalizer->normalize($staging->phone)['normalized'] ?? null;
            } catch (\Throwable) {
            }
        }

        $payload = [
            'name' => $staging->name,
            'email' => $staging->email,
            'country_iso' => $staging->country_iso,
            'emirate' => $staging->emirate,
            'relationship_type' => $staging->relationship_type,
            'confidence_level' => $staging->confidence_level,
            'unit_reference' => $staging->raw_unit_reference,
            'normalized_phone' => $normalizedPhone,
        ];

        $client = DB::transaction(function () use (
            $payload, $enricher, $normalizedPhone, $confirmedIds, $staging, $reviewItem
        ): Client {
            $client = $enricher->resolveClient($payload);

            if ($normalizedPhone) {
                ClientPhoneNumber::where('normalized_phone', $normalizedPhone)
                    ->whereNull('client_id')
                    ->update(['client_id' => $client->id]);
            }

            $enricher->syncOwnership(
                client: $client,
                payload: $payload,
                officialAreaId: $confirmedIds['official_area_id'],
                marketingAreaId: $confirmedIds['marketing_area_id'],
                projectId: $confirmedIds['project_id'],
                buildingId: $confirmedIds['building_id'],
                sourceName: $staging->source ?? 'review',
            );

            $staging->update(['status' => ImportStaging::STATUS_MATCHED]);

            $reviewItem->update([
                'resolution' => ImportReviewQueue::RESOLUTION_APPROVED,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);

            return $client;
        });

        return $client;
    }
}
