<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientScoringService
{
    // These marketing area names are considered premium for wealth scoring.
    // Listed in descending prestige order; first match wins the premium bonus.
    private const PREMIUM_AREAS = [
        'Saadiyat Island',
        'Yas Island',
        'Downtown Dubai',
        'Palm Jumeirah',
        'Dubai Marina',
        'DIFC',
        'District One',
        'Al Reem Island',
        'Al Maryah Island',
        'Jumeirah Gate',
        'Dubai Hills Estate',
        'Dubai Harbour',
        'Bluewaters Island',
        'Business Bay',
        'Burj Khalifa',
        'Al Raha Beach',
        'Jumeirah Lakes Towers',
        'Jumeirah Islands',
        'Ramhan Island',
    ];

    public const TIERS = [
        'standard' => [0,  24],
        'premium' => [25, 49],
        'high_net_worth' => [50, 74],
        'vip' => [75, 100],
    ];

    public const TIER_LABELS = [
        'standard' => 'Standard',
        'premium' => 'Premium',
        'high_net_worth' => 'High Net Worth',
        'vip' => 'VIP',
    ];

    /**
     * Recompute scores for multiple clients efficiently.
     * Processes in chunks to avoid loading large datasets into memory.
     *
     * @param  int[]  $clientIds
     */
    public function recomputeBulk(array $clientIds): void
    {
        if (empty($clientIds)) {
            return;
        }

        foreach (array_chunk(array_unique($clientIds), 500) as $chunk) {
            $this->processChunk($chunk);
        }
    }

    /**
     * Recompute all clients in the database. Used for the initial backfill.
     */
    public function recomputeAll(): void
    {
        Client::query()->select('id')->orderBy('id')->chunk(500, function ($clients): void {
            $this->processChunk($clients->pluck('id')->all());
        });
    }

    private function processChunk(array $clientIds): void
    {
        // Load what we need in two bulk queries — no N+1
        $clients = Client::query()
            ->whereIn('id', $clientIds)
            ->select(['id', 'full_name', 'emirate', 'nationality', 'gender', 'interest', 'country_iso', 'tier'])
            ->get()
            ->keyBy('id');

        // Phone counts per client
        $phoneCounts = DB::table('client_phone_numbers')
            ->whereIn('client_id', $clientIds)
            ->selectRaw('client_id, count(*) as cnt')
            ->groupBy('client_id')
            ->pluck('cnt', 'client_id');

        // Email counts per client
        $emailCounts = DB::table('client_emails')
            ->whereIn('client_id', $clientIds)
            ->selectRaw('client_id, count(*) as cnt')
            ->groupBy('client_id')
            ->pluck('cnt', 'client_id');

        // Source counts per client
        $sourceCounts = DB::table('client_sources')
            ->whereIn('client_id', $clientIds)
            ->selectRaw('client_id, count(*) as cnt')
            ->groupBy('client_id')
            ->pluck('cnt', 'client_id');

        // Ownership data per client — area names + relationship types + emirate
        $ownerships = DB::table('ownerships as o')
            ->join('marketing_areas as ma', 'ma.id', '=', 'o.marketing_area_id')
            ->whereIn('o.client_id', $clientIds)
            ->select(['o.client_id', 'o.relationship_type', 'o.emirate', 'ma.name as area_name'])
            ->get()
            ->groupBy('client_id');

        $premiumSet = array_flip(self::PREMIUM_AREAS);
        $now = now()->toDateTimeString();

        $updates = [];

        foreach ($clients as $clientId => $client) {
            $clientOwnerships = $ownerships->get($clientId, collect());

            $wealth = $this->computeWealth($clientOwnerships, $premiumSet);
            $completeness = $this->computeCompleteness(
                $client,
                (int) ($phoneCounts[$clientId] ?? 0),
                (int) ($emailCounts[$clientId] ?? 0),
                (int) ($clientOwnerships->count()),
                (int) ($sourceCounts[$clientId] ?? 0),
            );

            // Only auto-assign tier when the contact has no manually-set tier
            $tier = $client->tier ?? $this->tierFromScore($wealth);

            $updates[] = [
                'id' => $clientId,
                'wealth_score' => $wealth,
                'completeness_score' => $completeness,
                'tier' => $tier,
                'updated_at' => $now,
            ];
        }

        // Single bulk upsert — one query for the whole chunk
        foreach (array_chunk($updates, 500) as $batch) {
            DB::table('clients')->upsert(
                $batch,
                ['id'],
                ['wealth_score', 'completeness_score', 'tier', 'updated_at'],
            );
        }
    }

    private function computeWealth(Collection $ownerships, array $premiumSet): int
    {
        if ($ownerships->isEmpty()) {
            return 0;
        }

        $score = 0;

        // Property count (up to 40 pts)
        $count = $ownerships->count();
        $score += match (true) {
            $count >= 5 => 40,
            $count >= 3 => 30,
            $count >= 2 => 20,
            default => 10,
        };

        // Premium area bonus — up to 30 pts (10 per premium area, max 3)
        $premiumCount = $ownerships
            ->filter(fn ($o) => isset($premiumSet[$o->area_name]))
            ->pluck('area_name')
            ->unique()
            ->count();
        $score += min($premiumCount * 10, 30);

        // Relationship type bonus (highest value wins, up to 20 pts)
        $types = $ownerships->pluck('relationship_type')->unique()->all();
        $typeScore = 0;
        foreach ($types as $type) {
            $typeScore = max($typeScore, match ($type) {
                'investor' => 20,
                'owner' => 15,
                'past_owner' => 10,
                'buyer_interest' => 8,
                'seller_interest' => 8,
                'tenant' => 5,
                'resident' => 3,
                default => 0,
            });
        }
        $score += $typeScore;

        // Portfolio diversity bonus (up to 10 pts)
        $distinctEmirates = $ownerships->pluck('emirate')->filter()->unique()->count();
        $distinctAreas = $ownerships->pluck('area_name')->unique()->count();

        if ($distinctEmirates >= 2) {
            $score += 5;
        }
        if ($distinctAreas >= 3) {
            $score += 5;
        }

        return min($score, 100);
    }

    private function computeCompleteness(
        Client $client,
        int $phoneCount,
        int $emailCount,
        int $ownershipCount,
        int $sourceCount,
    ): int {
        $score = 0;

        if (filled($client->full_name)) {
            $score += 25;
        } // Most important
        if ($phoneCount > 0) {
            $score += 25;
        }
        if ($emailCount > 0) {
            $score += 15;
        }
        if (filled($client->emirate)) {
            $score += 15;
        }
        if ($ownershipCount > 0) {
            $score += 10;
        }
        if (filled($client->nationality)) {
            $score += 5;
        }
        if ($sourceCount > 0) {
            $score += 5;
        }

        return $score;
    }

    private function tierFromScore(int $score): string
    {
        return match (true) {
            $score >= 75 => 'vip',
            $score >= 50 => 'high_net_worth',
            $score >= 25 => 'premium',
            default => 'standard',
        };
    }
}
