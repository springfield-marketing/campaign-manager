<?php

namespace App\Filament\Resources\IvrScripts\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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

                FileUpload::make('audio_file_path')
                    ->label('Audio File (optional)')
                    ->disk('local')
                    ->directory('ivr/scripts/audio')
                    ->storeFileNamesIn('audio_original_name')
                    ->acceptedFileTypes(['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac', 'audio/x-m4a'])
                    ->maxSize(102400)
                    ->helperText('MP3, WAV, OGG, M4A or AAC — max 100 MB.'),

                Textarea::make('audio_script')
                    ->label('Script Text (optional)')
                    ->rows(6)
                    ->helperText('The text of the IVR script (for reference).'),
            ]),
        ]);
    }
}
