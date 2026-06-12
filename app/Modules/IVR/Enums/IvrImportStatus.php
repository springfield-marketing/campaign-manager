<?php

namespace App\Modules\IVR\Enums;

use Filament\Support\Contracts\HasLabel;

enum IvrImportStatus: string implements HasLabel
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

    public function getLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
