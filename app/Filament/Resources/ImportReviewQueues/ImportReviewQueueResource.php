<?php

namespace App\Filament\Resources\ImportReviewQueues;

use App\Filament\Resources\ImportReviewQueues\Pages\CreateImportReviewQueue;
use App\Filament\Resources\ImportReviewQueues\Pages\EditImportReviewQueue;
use App\Filament\Resources\ImportReviewQueues\Pages\ListImportReviewQueues;
use App\Filament\Resources\ImportReviewQueues\Schemas\ImportReviewQueueForm;
use App\Filament\Resources\ImportReviewQueues\Tables\ImportReviewQueuesTable;
use App\Models\ImportReviewQueue;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ImportReviewQueueResource extends Resource
{
    protected static ?string $model = ImportReviewQueue::class;


    public static function form(Schema $schema): Schema
    {
        return ImportReviewQueueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportReviewQueuesTable::configure($table);
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Imports';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getModelLabel(): string
    {
        return 'Review Item';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Review Queue';
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportReviewQueues::route('/'),
            'create' => CreateImportReviewQueue::route('/create'),
            'edit' => EditImportReviewQueue::route('/{record}/edit'),
        ];
    }
}
