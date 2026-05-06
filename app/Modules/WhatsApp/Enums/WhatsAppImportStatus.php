<?php

namespace App\Modules\WhatsApp\Enums;

enum WhatsAppImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Reverting = 'reverting';
    case Reverted = 'reverted';
    case RevertFailed = 'revert_failed';
}
