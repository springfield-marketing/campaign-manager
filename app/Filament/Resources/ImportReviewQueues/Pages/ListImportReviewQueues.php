<?php

namespace App\Filament\Resources\ImportReviewQueues\Pages;

use App\Filament\Resources\ImportReviewQueues\ImportReviewQueueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImportReviewQueues extends ListRecords
{
    protected static string $resource = ImportReviewQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
