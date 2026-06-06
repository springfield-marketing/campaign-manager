<?php

namespace App\Filament\Resources\ImportStagings\Schemas;

use Filament\Schemas\Schema;

// Staging rows are never created or edited manually — this form is intentionally empty.
class ImportStagingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
