<?php

namespace App\Filament\Resources\ImportReviewQueues\Pages;

use App\Filament\Resources\ImportReviewQueues\ImportReviewQueueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImportReviewQueue extends EditRecord
{
    protected static string $resource = ImportReviewQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
