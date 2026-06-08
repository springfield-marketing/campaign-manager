<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhoneNumbersRelationManager extends RelationManager
{
    protected static string $relationship = 'phoneNumbers';
    protected static ?string $title = 'Phone Numbers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('raw_phone')
                    ->label('Phone Number')
                    ->required()
                    ->maxLength(30)
                    ->helperText('Enter in international format, e.g. +971501234567'),

                Toggle::make('is_primary')
                    ->label('Primary Number')
                    ->default(false),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('normalized_phone')
                    ->label('Normalised Phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('raw_phone')
                    ->label('Raw')
                    ->placeholder('—'),

                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),

                TextColumn::make('detected_country')
                    ->label('Country')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('usage_status')
                    ->label('IVR Status')
                    ->badge()
                    ->color(fn (?string $state) => match($state) {
                        'active'   => 'success',
                        'cooldown' => 'warning',
                        'dead'     => 'danger',
                        default    => 'gray',
                    })
                    ->placeholder('active'),

                TextColumn::make('last_imported_at')
                    ->label('Last Import')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('is_primary', 'desc')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
