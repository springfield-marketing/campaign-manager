<?php

namespace App\Support;

use App\Models\ContactSuppression;

/**
 * Builds a cross-channel opt-out history for a phone number: every contact_suppression on the
 * same client_phone_number_id (IVR + WhatsApp, active and released), newest first. Used by the
 * DNC detail pages so you can see the full opt-out picture for a number, not just one record.
 */
class SuppressionHistory
{
    /**
     * @return list<string>  One human-readable line per suppression.
     */
    public static function lines(ContactSuppression $record): array
    {
        if ($record->client_phone_number_id === null) {
            return [];
        }

        return ContactSuppression::query()
            ->where('client_phone_number_id', $record->client_phone_number_id)
            ->orderByDesc('suppressed_at')
            ->get()
            ->map(function (ContactSuppression $s): string {
                $channel = strtoupper((string) ($s->channel ?? 'unknown'));
                $reason  = $s->reason ? ucwords(str_replace('_', ' ', $s->reason)) : 'Suppressed';
                $when    = $s->suppressed_at?->format('d M Y H:i') ?? '—';
                $state   = $s->released_at === null
                    ? 'active'
                    : 'released '.$s->released_at->format('d M Y');
                $platform = $s->platform ? ' ('.$s->platform.')' : '';

                return "{$channel}{$platform} · {$reason} · {$when} · {$state}";
            })
            ->all();
    }
}
