<?php

namespace App\Support;

use App\Models\ContactSuppression;

/**
 * Human-readable labels for WhatsApp DNC (contact_suppressions, channel = whatsapp).
 * Mirror of {@see IvrSuppressionDisplay} so the DNC list and the detail page stay in sync.
 */
class WhatsAppSuppressionDisplay
{
    public static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'opted_out'             => 'Opted Out',
            'manual'                => 'Manual',
            'customer_unsubscribed' => 'Customer Opt Out',
            default                 => $reason ? ucwords(str_replace('_', ' ', $reason)) : 'Suppressed',
        };
    }

    public static function sourceLabel(ContactSuppression $suppression): string
    {
        $context = $suppression->context ?? [];

        return match (true) {
            ($context['source'] ?? null) === 'import'      => 'DNC Import',
            ($context['source'] ?? null) === 'manual'      => 'Manual Entry',
            ($context['source'] ?? null) === 'manual_bulk' => 'Bulk Action',
            isset($context['campaign_id'])                 => 'Campaign Opt Out',
            default                                        => self::reasonLabel($suppression->reason),
        };
    }

    public static function detailLabel(ContactSuppression $suppression): string
    {
        $context = $suppression->context ?? [];

        if ($context['reason'] ?? null) {
            return (string) $context['reason'];
        }

        return self::provenanceLabel($suppression);
    }

    /**
     * Where the opt-out came from (file / campaign), excluding the free-text reason — used where
     * the reason is shown in its own field (e.g. the DNC detail page) to avoid duplicating it.
     */
    public static function provenanceLabel(ContactSuppression $suppression): string
    {
        $context = $suppression->context ?? [];

        if ($context['source_file'] ?? null) {
            return (string) $context['source_file'];
        }

        if ($context['first_import']['file'] ?? null) {
            return (string) $context['first_import']['file'];
        }

        if ($context['campaign_id'] ?? null) {
            return 'Campaign '.$context['campaign_id'];
        }

        return '—';
    }
}
