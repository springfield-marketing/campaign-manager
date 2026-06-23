<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\RestrictsToAdmin;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use RestrictsToAdmin;

    protected static ?string $model = User::class;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-users'; }
    public static function getNavigationGroup(): ?string { return 'System'; }
    public static function getNavigationSort(): ?int { return 10; }
    public static function getNavigationLabel(): string { return 'Users'; }
    public static function getModelLabel(): string { return 'User'; }
    public static function getPluralModelLabel(): string { return 'Users'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'users';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
