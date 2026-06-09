<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsAppCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign Details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Campaign Name')
                        ->required()
                        ->maxLength(255),

                    DateTimePicker::make('started_at')
                        ->label('Started At')
                        ->seconds(false),

                    DateTimePicker::make('completed_at')
                        ->label('Completed At')
                        ->seconds(false),
                ]),
        ]);
    }
}
