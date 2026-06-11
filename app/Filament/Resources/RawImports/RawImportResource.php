<?php

namespace App\Filament\Resources\RawImports;

use App\Filament\Resources\RawImports\Pages\ViewRawImport;
use App\Filament\Resources\RawImports\RelationManagers\ImportedContactsRelationManager;
use App\Modules\IVR\Models\IvrImport;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RawImportResource extends Resource
{
    protected static ?string $model = IvrImport::class;

    public static function getNavigationGroup(): ?string { return null; }
    public static function shouldRegisterNavigation(): bool { return false; }
    public static function getSlug(?\Filament\Panel $panel = null): string { return 'raw-imports'; }
    public static function getModelLabel(): string { return 'Raw Import'; }
    public static function getPluralModelLabel(): string { return 'Raw Imports'; }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return url('/admin/import-stagings');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'raw_contacts');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Import Summary')
                ->columns(4)
                ->schema([
                    TextEntry::make('original_file_name')
                        ->label('File')
                        ->columnSpan(2),

                    TextEntry::make('source_name')
                        ->label('Source')
                        ->placeholder('—'),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            'completed'             => 'success',
                            'completed_with_errors' => 'warning',
                            'processing', 'pending' => 'info',
                            'failed'                => 'danger',
                            default                 => 'gray',
                        })
                        ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),

                    TextEntry::make('total_rows')
                        ->label('Total rows')
                        ->numeric(),

                    TextEntry::make('successful_rows')
                        ->label('Imported')
                        ->numeric()
                        ->color('success'),

                    TextEntry::make('duplicate_rows')
                        ->label('Duplicates')
                        ->numeric()
                        ->color('gray'),

                    TextEntry::make('failed_rows')
                        ->label('Failed')
                        ->numeric()
                        ->color(fn (?int $state) => ($state ?? 0) > 0 ? 'danger' : 'gray'),

                    TextEntry::make('started_at')
                        ->label('Started')
                        ->dateTime('d M Y H:i'),

                    TextEntry::make('completed_at')
                        ->label('Completed')
                        ->dateTime('d M Y H:i')
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getRelations(): array
    {
        return [
            ImportedContactsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewRawImport::route('/{record}'),
        ];
    }
}
