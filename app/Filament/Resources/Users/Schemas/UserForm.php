<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('role')
                    ->options(UserRole::class)
                    ->default(UserRole::Ivr)
                    ->required()
                    ->helperText('Administrator sees everything. IVR and WhatsApp roles see only their own module plus Contacts.'),

                // 'password' has a 'hashed' cast on the model, so the plain value is hashed on save —
                // never hash here. Only persist when a value is typed, so editing without entering a
                // password keeps the existing one.
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255)
                    ->helperText('Leave blank when editing to keep the current password.'),
            ]),
        ]);
    }
}
