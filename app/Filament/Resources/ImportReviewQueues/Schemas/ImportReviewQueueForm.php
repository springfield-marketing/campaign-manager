<?php

namespace App\Filament\Resources\ImportReviewQueues\Schemas;

use Filament\Schemas\Schema;

// Review queue rows are resolved via table actions (Approve / Reject), not form edits.
class ImportReviewQueueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
