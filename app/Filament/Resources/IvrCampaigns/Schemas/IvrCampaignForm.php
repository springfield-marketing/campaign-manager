<?php

namespace App\Filament\Resources\IvrCampaigns\Schemas;

use Filament\Schemas\Schema;

// Campaign records are created by the import processor, not manually.
class IvrCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
