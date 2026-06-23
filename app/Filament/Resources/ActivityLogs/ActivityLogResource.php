<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Concerns\RestrictsToAdmin;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    use RestrictsToAdmin;

    protected static ?string $model = ActivityLog::class;

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getNavigationIcon(): string { return 'heroicon-o-clipboard-document-list'; }
    public static function getNavigationGroup(): ?string { return 'System'; }
    public static function getNavigationSort(): ?int { return 20; }
    public static function getNavigationLabel(): string { return 'Activity Log'; }
    public static function getModelLabel(): string { return 'Activity'; }
    public static function getPluralModelLabel(): string { return 'Activity Log'; }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'activity-log';
    }

    // Read-only: the log is written by the app, never created/edited by hand.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
