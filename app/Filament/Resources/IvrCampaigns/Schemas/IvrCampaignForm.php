<?php

namespace App\Filament\Resources\IvrCampaigns\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IvrCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign Details')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Campaign Name / ID')
                        ->disabled(),

                    TextInput::make('external_campaign_id')
                        ->label('External ID')
                        ->disabled(),

                    TextInput::make('platform')
                        ->disabled(),

                    TextInput::make('state')
                        ->disabled(),
                ]),
        ]);
    }
}
