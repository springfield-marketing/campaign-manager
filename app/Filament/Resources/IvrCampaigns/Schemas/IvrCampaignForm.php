<?php

namespace App\Filament\Resources\IvrCampaigns\Schemas;

use App\Modules\IVR\Models\IvrCampaign;
use App\Modules\IVR\Models\IvrScript;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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

            Section::make('Script')
                ->columnSpanFull()
                ->schema([
                    Select::make('ivr_script_id')
                        ->label('IVR Script')
                        ->options(fn () => IvrScript::orderBy('name')->pluck('name', 'id'))
                        ->nullable()
                        ->placeholder('— No script —')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('The script this campaign used. Change it here if the wrong one was attached, then Save.'),

                    Placeholder::make('script_preview')
                        ->label('Script Content')
                        ->content(function (Get $get, ?IvrCampaign $record): HtmlString {
                            $scriptId = $get('ivr_script_id');
                            $script = $scriptId ? IvrScript::find($scriptId) : null;

                            // A linked library script wins; otherwise fall back to the campaign's
                            // own inline audio copy (set when an import had no linked script).
                            $audioName = $script?->audio_original_name ?? $record?->audio_original_name;
                            $text = trim((string) ($script?->audio_script ?? $record?->audio_script ?? ''));

                            $html = '';
                            if ($audioName) {
                                $html .= '<div class="text-sm"><span class="font-medium">Audio file:</span> ' . e($audioName) . '</div>';
                            }
                            $html .= $text !== ''
                                ? '<div class="text-sm whitespace-pre-wrap mt-1">' . e($text) . '</div>'
                                : '<span class="text-sm text-gray-400">No script text recorded.</span>';

                            return new HtmlString($html);
                        }),
                ]),
        ]);
    }
}
