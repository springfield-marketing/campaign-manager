<?php

namespace App\Support;

use App\Models\ContactSuppression;

class IvrSuppressionDisplay
{
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'active', null => 'Ready to Call',
            'cooldown' => 'Resting',
            'inactive' => 'Resting',
            'dead' => 'Not Callable',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'active', null => 'success',
            'cooldown' => 'warning',
            'inactive' => 'warning',
            'dead' => 'danger',
            default => 'gray',
        };
    }

    public static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'unsubscribe' => 'Imported Do Not Call',
            'customer_unsubscribed' => 'Customer Opt Out',
            default => $reason ? ucwords(str_replace('_', ' ', $reason)) : 'Do Not Call',
        };
    }

    public static function sourceLabel(ContactSuppression $suppression): string
    {
        $context = $suppression->context ?? [];

        if (($context['source'] ?? null) === 'historical_unsubscriber_restore') {
            return 'Historical DND import';
        }

        if (($context['source'] ?? null) === 'unsubscriber_import') {
            return 'DND import';
        }

        if (($context['source'] ?? null) === 'manual') {
            return 'Manual entry';
        }

        if (($context['source'] ?? null) === 'manual_bulk') {
            return 'Manual bulk action';
        }

        if ($context['campaign_id'] ?? null) {
            return 'Campaign result';
        }

        return self::reasonLabel($suppression->reason);
    }

    public static function detailLabel(ContactSuppression $suppression): string
    {
        $context = $suppression->context ?? [];

        if ($context['reason'] ?? null) {
            return (string) $context['reason'];
        }

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
