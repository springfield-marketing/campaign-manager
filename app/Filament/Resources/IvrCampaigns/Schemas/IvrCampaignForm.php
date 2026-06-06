<?php

namespace App\Filament\Resources\IvrCampaigns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IvrCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign Details')
                ->columns(2)
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

            Section::make('Call Statistics')
                ->columns(3)
                ->schema([
                    TextInput::make('total_calls')
                        ->label('Total Calls')
                        ->numeric()
                        ->disabled(),

                    TextInput::make('answered_calls')
                        ->label('Answered')
                        ->numeric()
                        ->disabled(),

                    TextInput::make('unanswered_calls')
                        ->label('Unanswered')
                        ->numeric()
                        ->disabled(),

                    TextInput::make('leads_count')
                        ->label('Leads (Interested)')
                        ->numeric()
                        ->disabled(),

                    TextInput::make('more_info_count')
                        ->label('More Info')
                        ->numeric()
                        ->disabled(),

                    TextInput::make('unsubscribed_count')
                        ->label('Unsubscribed')
                        ->numeric()
                        ->disabled(),
                ]),

            Section::make('Timing & Credits')
                ->columns(3)
                ->schema([
                    DateTimePicker::make('started_at')
                        ->label('Started At')
                        ->disabled(),

                    DateTimePicker::make('completed_at')
                        ->label('Completed At')
                        ->disabled(),

                    TextInput::make('credits_used')
                        ->label('Credits Used')
                        ->numeric()
                        ->disabled(),
                ]),
        ]);
    }
}
