<?php

namespace App\Filament\Resources\IvrScripts\Schemas;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IvrScriptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Script Name')
                    ->required()
                    ->maxLength(255),

                Textarea::make('audio_script')
                    ->label('Script Text')
                    ->rows(6)
                    ->helperText('The text of the IVR script (for reference).'),
            ]),
        ]);
    }
}
