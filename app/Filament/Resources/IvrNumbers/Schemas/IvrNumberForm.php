<?php

namespace App\Filament\Resources\IvrNumbers\Schemas;

use Filament\Schemas\Schema;

// IVR numbers come from imports — edit via the Contacts resource, not here.
class IvrNumberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
