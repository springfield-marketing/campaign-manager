<?php

namespace App\Filament\Resources\IvrImports\Schemas;

use Filament\Schemas\Schema;

// IVR imports are created via the Upload action on the list page, not this form.
class IvrImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
