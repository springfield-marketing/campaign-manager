<?php

namespace App\Modules\IVR\Enums;

enum IvrImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Deleting = 'deleting';
    case Deleted = 'deleted';
    case DeleteFailed = 'delete_failed';
    case Reverting = 'reverting';
    case Reverted = 'reverted';
    case RevertFailed = 'revert_failed';
}
