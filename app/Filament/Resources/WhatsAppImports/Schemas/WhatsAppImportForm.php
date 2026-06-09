<?php

namespace App\Filament\Resources\WhatsAppImports\Schemas;

use App\Modules\WhatsApp\Models\WhatsAppImport;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsAppImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Import Details')
                ->columns(2)
                ->schema([
                    TextInput::make('original_file_name')
                        ->label('File Name')
                        ->disabled(),

                    TextInput::make('source_name')
                        ->label('Source Name')
                        ->disabled(),

                    TextInput::make('type')
                        ->label('Type')
                        ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                        ->disabled(),

                    TextInput::make('status')
                        ->label('Status')
                        ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—')
                        ->disabled(),

                    Placeholder::make('error_message')
                        ->label('Error')
                        ->content(fn (WhatsAppImport $record): string => $record->error_message ?? '—')
                        ->columnSpanFull(),
                ]),

            Section::make('Processing Summary')
                ->columns(4)
                ->schema([
                    TextInput::make('total_rows')
                        ->label('Total')
                        ->numeric()
                        ->disabled(),

                    TextInput::make('successful_rows')
                        ->label('Successful')
                        ->numeric()
                        ->disabled(),

                    TextInput::make('failed_rows')
                        ->label('Failed')
                        ->numeric()
                        ->disabled(),

                    TextInput::make('duplicate_rows')
                        ->label('Duplicates')
                        ->numeric()
                        ->disabled(),
                ]),

            Section::make('Timing')
                ->columns(2)
                ->schema([
                    Placeholder::make('started_at')
                        ->label('Started At')
                        ->content(fn (WhatsAppImport $record): string => $record->started_at?->format('d M Y H:i') ?? '—'),

                    Placeholder::make('completed_at')
                        ->label('Completed At')
                        ->content(fn (WhatsAppImport $record): string => $record->completed_at?->format('d M Y H:i') ?? '—'),
                ]),
        ]);
    }
}
